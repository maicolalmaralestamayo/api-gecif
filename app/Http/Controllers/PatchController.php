<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PatchController extends WriteController
{
    // PATCH /{resource}/{id} — soporta include y fields en la respuesta (simétrico con GET).
    public function updateResource(Request $request, string $resource, string $id)
    {
        if ($response = $this->validateAcceptHeader($request)) { return $response; }
        if ($response = $this->validateContentType($request)) { return $response; }

        return $this->guard($request, function () use ($request, $resource, $id) {
            $this->assertUnknownQueryParams($request, ['include', 'fields']);

            $model = $this->newModel($resource);
            $body = $this->parseRequestBody($request);

            $this->validateCustomMemberNames($body);
            $this->validateCustomMemberNames($body['data'] ?? [], '/data');
            $this->rejectIncludedOnUpdate($body);
            $this->collectAndValidateLids($body);

            $data = $this->validateResourceData($body, $this->resourceTypeForModel($model), $id);

            [$includePaths, $includedParams, $fields, $relSorts] = $this->parseDocumentParams($request, $model);

            $attributes = $this->extractAttributes($data, $model);
            $relationships = $this->extractRelationships($data);
            $lid = isset($data['lid']) && is_string($data['lid']) ? $data['lid'] : null;

            $instance = $this->transaction(function () use ($model, $id, $attributes, $relationships) {
                $instance = $this->findModel($model, $id);
                $instance->fill($attributes);
                $instance->save();
                $this->syncRelationships($instance, $relationships);
                return $instance;
            });

            $this->loadCollectionIncludes(collect([$instance]), $includePaths, $includedParams);

            [$resourceData, $included] = $this->serializeData($instance, $request, $includePaths, $fields, $relSorts);

            if ($lid !== null) { $resourceData['lid'] = $lid; }

            return $this->jsonApi($request, $resourceData, 200, $included, ['self' => $this->linkValue($this->selfUrl($request, $instance), [], 'Self')], [], $fields);
        });
    }

    // PATCH /{resource}/{id}/relationships/{related}
    public function updateRelationship(Request $request, string $resource, string $id, string $related)
    {
        if ($response = $this->validateAcceptHeader($request)) { return $response; }
        if ($response = $this->validateContentType($request)) { return $response; }

        return $this->guard($request, function () use ($request, $resource, $id, $related) {
            $model = $this->newModel($resource);
            $instance = $this->findModel($model, $id);
            $relation = $this->getRelationOrFail($instance, $related);

            $body = $this->parseRequestBody($request);
            $this->validateCustomMemberNames($body);

            $data = $this->validateRelationshipData($body, $relation);

            $this->transaction(fn () => $this->syncRelationship($instance, $related, $data, true));

            return $this->jsonApi($request, $this->linkage($instance->{$related}()->get()), 200, [], $this->relationshipLinks($request, $instance, $related));
        });
    }
}