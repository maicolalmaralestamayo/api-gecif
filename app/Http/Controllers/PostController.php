<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class PostController extends WriteController
{
    // POST /{resource} — soporta include y fields en la respuesta (simétrico con GET/PATCH/PUT).
    public function createResource(Request $request, string $resource)
    {
        if ($response = $this->validateAcceptHeader($request)) { return $response; }
        if ($response = $this->validateContentType($request)) { return $response; }

        return $this->guard($request, function () use ($request, $resource) {
            $this->assertUnknownQueryParams($request, ['include', 'fields']);

            $model = $this->newModel($resource);
            $body = $this->parseRequestBody($request);

            $this->validateCustomMemberNames($body);
            $this->validateCustomMemberNames($body['data'] ?? [], '/data');

            foreach (($body['included'] ?? []) as $i => $res) {
                if (is_array($res)) { $this->validateCustomMemberNames($res, "/included/{$i}"); }
            }

            $this->collectAndValidateLids($body);

            $data = $this->validateResourceData($body, $this->resourceTypeForModel($model), null);

            [$includePaths, $includedParams, $fields, $relSorts] = $this->parseDocumentParams($request, $model);

            $included = $body['included'] ?? [];
            $lid = isset($data['lid']) && is_string($data['lid']) ? $data['lid'] : null;
            $compositeResult = null;

            $instance = $this->transaction(function () use ($body, $model, $included, &$compositeResult) {
                if (is_array($included) && $included !== []) {
                    $compositeResult = $this->createComposite($body, $model);

                    return $compositeResult['primary'];
                }

                $attributes = $this->extractAttributes($body['data'], $model);
                $relationships = $this->extractRelationships($body['data']);

                $instance = $model->newInstance();
                $instance->fill($attributes);
                $instance->save();
                $this->syncRelationships($instance, $relationships);

                return $instance;
            });

            $this->loadCollectionIncludes(collect([$instance]), $includePaths, $includedParams);

            [$resourceData, $includedResources] = $this->serializeData($instance, $request, $includePaths, $fields, $relSorts);

            if ($compositeResult !== null) {
                $primaryKey = $this->resourceKey($instance);
                $primaryType = $this->resourceTypeForModel($instance);
                $lidToRel = [];

                foreach (($body['data']['relationships'] ?? []) as $relName => $rel) {
                    $relData = $rel['data'] ?? null;

                    if ($relData === null) { continue; }

                    if (is_array($relData) && isset($relData['lid'])) {
                        $lidToRel[$relData['lid']] = $relName;
                    } elseif (is_array($relData)) {
                        foreach ($relData as $identifier) {
                            if (is_array($identifier) && isset($identifier['lid'])) { $lidToRel[$identifier['lid']] = $relName; }
                        }
                    }
                }

                $alreadyIncluded = [];

                foreach ($includedResources as $inc) {
                    $alreadyIncluded[$inc['type'] . ':' . $inc['id']] = true;
                }

                foreach ($compositeResult['map'] as $includedLid => $includedModel) {
                    $key = $this->resourceKey($includedModel);

                    if ($key === $primaryKey) { continue; }
                    if (isset($alreadyIncluded[$key])) { continue; }

                    $includedResources[] = $this->modelToResource($includedModel, $request, $fields, $relSorts, false, $includedLid);
                    $alreadyIncluded[$key] = true;

                    if (isset($lidToRel[$includedLid])) {
                        $this->includedOrigins[$key] = ['parentType' => $primaryType, 'relation' => $lidToRel[$includedLid]];
                    }
                }
            }

            if ($lid !== null) { $resourceData['lid'] = $lid; }

            $location = $this->selfUrl($request, $instance);

            return $this->jsonApi($request, $resourceData, 201, $includedResources, ['self' => $this->linkValue($location, [], 'Self')], [], $fields)
                ->header('Location', $location);
        });
    }

    // POST /{resource}/{id}/relationships/{related}
    public function addToRelationship(Request $request, string $resource, string $id, string $related)
    {
        if ($response = $this->validateAcceptHeader($request)) { return $response; }
        if ($response = $this->validateContentType($request)) { return $response; }

        return $this->guard($request, function () use ($request, $resource, $id, $related) {
            $model = $this->newModel($resource);
            $instance = $this->findModel($model, $id);
            $relation = $this->getRelationOrFail($instance, $related);

            if (! $this->isToMany($relation)) { throw $this->validationError('/data', "La relación '{$related}' no es a-muchos."); }

            $body = $this->parseRequestBody($request);
            $this->validateCustomMemberNames($body);

            $data = $this->validateRelationshipData($body, $relation);

            $this->transaction(fn () => $this->syncRelationship($instance, $related, $data, false));

            return $this->jsonApi($request, $this->linkage($instance->{$related}()->get()), 200, [], $this->relationshipLinks($request, $instance, $related));
        });
    }

    // Crea el recurso principal + los incluidos resolviendo lids en 3 fases (específico de POST).
    protected function createComposite(array $body, Model $primaryModel): array
    {
        $data = $body['data'];
        $included = $body['included'] ?? [];
        $map = [];
        $includedPending = [];

        $primary = $primaryModel;
        $primary->fill($this->extractAttributes($data, $primaryModel));
        $primary->save();

        if (isset($data['lid'])) { $map[$data['lid']] = $primary; }

        foreach ($included as $i => $resource) {
            if (! is_array($resource) || ! isset($resource['type'], $resource['lid'])) {
                throw $this->validationError("/included/{$i}", 'Cada recurso en "included" requiere "type" y "lid".', 400, 'invalid_included');
            }

            if (isset($resource['id'])) {
                throw $this->validationError("/included/{$i}/id", 'Un recurso en "included" con "lid" no debe tener "id".', 400, 'invalid_included');
            }

            $model = $this->newModel($resource['type']);

            if ($this->resourceTypeForModel($model) !== $resource['type']) {
                throw $this->validationError("/included/{$i}/type", "El tipo '{$resource['type']}' no coincide con el recurso.", 400, 'invalid_included');
            }

            $instance = $model->newInstance();
            $instance->fill($this->extractAttributes($resource, $model));

            foreach ($resource['relationships'] ?? [] as $name => $rel) {
                $relData = $rel['data'] ?? null;
                if (! is_array($relData) || isset($relData['id']) || ! isset($relData['lid'])) { continue; }

                $related = $map[$relData['lid']] ?? null;

                if ($related === null) { continue; }

                $relation = $instance->{$name}();

                if ($relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo) { $relation->associate($related); }
            }

            $instance->save();

            $map[$resource['lid']] = $instance;
            $includedPending[] = ['resource' => $resource, 'instance' => $instance];
        }

        $allPending = array_merge([['resource' => $data, 'instance' => $primary]], $includedPending);

        foreach ($allPending as $item) {
            $relationships = $item['resource']['relationships'] ?? [];

            if ($relationships === []) { continue; }

            $resolved = $this->resolveLidReferencesInResource($item['resource'], $map);

            $this->syncRelationships($item['instance'], $resolved['relationships'] ?? []);
        }

        return ['primary' => $primary, 'map' => $map];
    }

    // Resuelve referencias `lid` en las relaciones de un resource object (específico de POST composite).
    protected function resolveLidReferencesInResource(array $resource, array $lidMap): array
    {
        $relationships = $resource['relationships'] ?? [];

        foreach ($relationships as $name => $rel) {
            if (! isset($rel['data'])) { continue; }

            $relationships[$name]['data'] = $this->resolveLidInLinkage($rel['data'], $lidMap);
        }

        if ($relationships !== []) { $resource['relationships'] = $relationships; }

        return $resource;
    }

    // Reemplaza `{type, lid}` por `{type, id}` en un resource linkage (específico de POST composite).
    protected function resolveLidInLinkage(mixed $data, array $lidMap): mixed
    {
        if ($data === null) { return null; }
        if (! is_array($data)) { return $data; }

        if ($this->isList($data)) { return array_map(fn ($item) => $this->resolveLidInLinkage($item, $lidMap), $data); }

        if (! isset($data['lid'])) { return $data; }

        $model = $lidMap[$data['lid']] ?? null;

        if ($model === null) {
            throw $this->validationError('/data/relationships', "El lid '{$data['lid']}' no corresponde a ningún recurso en \"included\".", 400, 'unresolved_lid');
        }

        if (! isset($data['type']) || $this->resourceTypeForModel($model) !== $data['type']) {
            throw $this->validationError('/data/relationships', "El tipo '{$data['type']}' no coincide con el recurso referenciado por lid '{$data['lid']}'.", 400, 'unresolved_lid');
        }

        return ['type' => $data['type'], 'id' => (string) $model->getKey()];
    }
}