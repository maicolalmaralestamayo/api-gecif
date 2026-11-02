<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AtomicController extends WriteController
{
    // POST /operations (Atomic Operations extension).
    public function atomicOperations(Request $request)
    {
        if ($response = $this->validateAcceptHeader($request)) { return $response; }
        if ($response = $this->validateAtomicContentType($request)) { return $response; }

        return $this->guard($request, function () use ($request) {
            $this->assertUnknownQueryParams($request, []);

            $body = $this->parseRequestBody($request);
            $this->validateCustomMemberNames($body);

            if (isset($body['data']) || isset($body['included'])) { throw $this->validationError('/', 'El documento no puede incluir "data" ni "included".'); }

            $operations = $body['atomic:operations'] ?? null;

            if (! is_array($operations) || $operations === []) { throw $this->validationError('/atomic:operations', 'Debe incluir al menos una operación.'); }

            $results = $this->transaction(function () use ($operations, $request) {
                $lidMap = [];
                $out = [];

                foreach ($operations as $index => $operation) {
                    $out[] = $this->processAtomicOperation($operation, $index, $lidMap, $request);
                }

                return $out;
            });

            $this->appliedExtensions[] = self::ATOMIC_EXT;

            $allEmpty = true;

            foreach ($results as $r) {
                if (is_array($r) && $r !== []) { $allEmpty = false; break; }
                if (is_object($r) && (array) $r !== []) { $allEmpty = false; break; }
            }

            if ($allEmpty) { return response('', 204, ['Content-Type' => $this->responseContentType(), 'Vary' => 'Accept']); }

            return $this->atomicResponse($request, $results);
        });
    }

    // Documento JSON:API con `atomic:results` (específico de esta extensión).
    protected function atomicResponse(Request $request, array $results): JsonResponse
    {
        $payload = [
            'jsonapi' => $this->jsonApiObject(),
            'atomic:results' => $results,
            'meta' => $this->requestMeta($request),
        ];

        $links = $this->documentLinks([]);

        if ($links !== []) { $payload['links'] = $links; }

        return response()->json($this->mergeCustomMembers($payload), 200, ['Content-Type' => $this->responseContentType(), 'Vary' => 'Accept'], $this->jsonEncodeFlags);
    }

    // Valida Content-Type + ext="https://jsonapi.org/ext/atomic".
    protected function validateAtomicContentType(Request $request): ?JsonResponse
    {
        $contentType = $request->header('Content-Type', '');

        if (! str_contains($contentType, 'application/vnd.api+json')) {
            return $this->errorResponse($request, 415, 'Unsupported Media Type', 'El Content-Type debe ser application/vnd.api+json.', ['source' => ['header' => 'Content-Type']]);
        }

        if (! str_contains($contentType, 'ext="https://jsonapi.org/ext/atomic"')) {
            return $this->errorResponse($request, 415, 'Unsupported Media Type', 'El Content-Type debe incluir ext="https://jsonapi.org/ext/atomic".', ['source' => ['header' => 'Content-Type']]);
        }

        return null;
    }

    // Procesa una operación atómica individual.
    protected function processAtomicOperation(array $operation, int $index, array &$lidMap, Request $request): array|object
    {
        $op = $operation['op'] ?? null;

        if (! in_array($op, ['add', 'update', 'replace', 'remove'], true)) { throw $this->validationError("/atomic:operations/{$index}/op", 'La operación debe ser "add", "update", "replace" o "remove".'); }

        $ref = $operation['ref'] ?? null;
        $relationship = is_array($ref) ? ($ref['relationship'] ?? null) : null;

        if ($relationship !== null) { return $this->processAtomicRelationship($operation, $index, $lidMap); }

        if ($op === 'add') { return $this->processAtomicAddResource($operation, $index, $lidMap, $request); }
        if ($op === 'update') { return $this->processAtomicUpdateResource($operation, $index, $lidMap, $request); }
        if ($op === 'replace') { return $this->processAtomicReplaceResource($operation, $index, $lidMap, $request); }

        return $this->processAtomicRemoveResource($operation, $index, $lidMap);
    }

    // add: crea un recurso (con lid y relaciones opcionales).
    protected function processAtomicAddResource(array $operation, int $index, array &$lidMap, Request $request): array
    {
        $data = $operation['data'] ?? null;

        if (! is_array($data)) { throw $this->validationError("/atomic:operations/{$index}/data", 'La operación "add" requiere "data".'); }

        $this->validateCustomMemberNames($data, "/atomic:operations/{$index}/data");

        $type = $data['type'] ?? null;

        if ($type === null) { throw $this->validationError("/atomic:operations/{$index}/data/type", 'El recurso debe incluir "type".'); }
        if (isset($data['id'])) { throw $this->validationError("/atomic:operations/{$index}/data/id", 'No se admiten IDs generados por el cliente en "add".'); }

        $model = $this->newModel($type);

        if ($this->resourceTypeForModel($model) !== $type) { throw $this->validationError("/atomic:operations/{$index}/data/type", "El tipo '{$type}' no coincide con el recurso."); }

        $instance = $model->newInstance();
        $instance->fill($this->extractAttributes($data, $model));
        $instance->save();

        if (isset($data['relationships'])) { $this->syncRelationshipsWithLid($instance, $data['relationships'], $lidMap, $index); }

        if (isset($data['lid'])) { $lidMap[$data['lid']] = $instance; }

        return ['data' => $this->modelToResource($instance, $request)];
    }

    // update: actualiza un recurso.
    protected function processAtomicUpdateResource(array $operation, int $index, array &$lidMap, Request $request): array
    {
        $instance = $this->resolveAtomicTargetResource($operation, $index, $lidMap);
        $data = $operation['data'] ?? null;

        if (! is_array($data)) { throw $this->validationError("/atomic:operations/{$index}/data", 'La operación "update" requiere "data".'); }

        $this->validateCustomMemberNames($data, "/atomic:operations/{$index}/data");

        if (isset($data['type']) && $this->resourceTypeForModel($instance) !== $data['type']) { throw $this->validationError("/atomic:operations/{$index}/data/type", 'El tipo no coincide con el recurso objetivo.'); }

        $model = $instance->newInstance();

        if (isset($data['attributes'])) {
            $instance->fill($this->extractAttributes($data, $model));
            $instance->save();
        }

        if (isset($data['relationships'])) { $this->syncRelationshipsWithLid($instance, $data['relationships'], $lidMap, $index); }

        return ['data' => $this->modelToResource($instance, $request)];
    }

    // replace: reemplaza el recurso completo.
    protected function processAtomicReplaceResource(array $operation, int $index, array &$lidMap, Request $request): array
    {
        $instance = $this->resolveAtomicTargetResource($operation, $index, $lidMap);
        $data = $operation['data'] ?? null;

        if (! is_array($data)) { throw $this->validationError("/atomic:operations/{$index}/data", 'La operación "replace" requiere "data".'); }

        $this->validateCustomMemberNames($data, "/atomic:operations/{$index}/data");

        if (isset($data['type']) && $this->resourceTypeForModel($instance) !== $data['type']) { throw $this->validationError("/atomic:operations/{$index}/data/type", 'El tipo no coincide con el recurso objetivo.'); }

        $relationships = $data['relationships'] ?? [];
        $resolvedRelationships = [];

        foreach ($relationships as $name => $relationship) {
            $relData = $relationship['data'] ?? null;
            $resolvedRelationships[$name] = ['data' => $this->resolveAtomicLinkage($relData, $lidMap, $index)];
        }

        $data['relationships'] = $resolvedRelationships;

        $this->applyFullReplacement($instance, $data);

        return ['data' => $this->modelToResource($instance, $request)];
    }

    // remove: elimina un recurso por ref.
    protected function processAtomicRemoveResource(array $operation, int $index, array &$lidMap): array|object
    {
        $instance = $this->resolveAtomicTargetResource($operation, $index, $lidMap);
        $instance->delete();

        return (object) [];
    }

    // Procesa operaciones sobre relaciones (add/update/remove en to-one y to-many).
    protected function processAtomicRelationship(array $operation, int $index, array &$lidMap): array|object
    {
        $op = $operation['op'];
        $ref = $operation['ref'];
        $model = $this->resolveAtomicRefModel($ref, $index, $lidMap);
        $relationship = $ref['relationship'];
        $relation = $this->getRelationOrFail($model, $relationship);
        $data = $operation['data'] ?? null;

        if ($op === 'add') {
            if (! $this->isToMany($relation)) { throw $this->validationError("/atomic:operations/{$index}/ref/relationship", 'Solo se puede añadir a relaciones a-muchos.'); }
            if (! is_array($data) || ! $this->isList($data)) { throw $this->validationError("/atomic:operations/{$index}/data", 'Para "add" en relación a-muchos, "data" debe ser un array de identificadores.'); }

            $resolved = $this->resolveAtomicLinkage($data, $lidMap, $index);
            $this->syncRelationship($model, $relationship, $resolved, false);

            return (object) [];
        }

        if ($op === 'update') {
            $resolved = $this->resolveAtomicLinkage($data, $lidMap, $index);
            $this->syncRelationship($model, $relationship, $resolved, true);

            return (object) [];
        }

        if ($op === 'remove') {
            if ($this->isToMany($relation)) {
                if (! is_array($data) || ! $this->isList($data)) { throw $this->validationError("/atomic:operations/{$index}/data", 'Para "remove" en relación a-muchos, "data" debe ser un array de identificadores.'); }

                $resolved = $this->resolveAtomicLinkage($data, $lidMap, $index);
                $this->syncRelationship($model, $relationship, $resolved, false, true);
            } else {
                $this->syncRelationship($model, $relationship, null, true);
            }

            return (object) [];
        }

        throw $this->validationError("/atomic:operations/{$index}/op", 'Operación no soportada para relaciones.');
    }

    // Resuelve el recurso objetivo (sin relación).
    protected function resolveAtomicTargetResource(array $operation, int $index, array &$lidMap): Model
    {
        $ref = $operation['ref'] ?? null;

        if ($ref !== null) {
            if (! is_array($ref)) { throw $this->validationError("/atomic:operations/{$index}/ref", '"ref" debe ser un objeto.'); }
            if (isset($ref['relationship'])) { throw $this->validationError("/atomic:operations/{$index}/ref/relationship", 'Use "update"/"replace"/"remove" de relación, no de recurso.'); }

            return $this->resolveAtomicRefModel($ref, $index, $lidMap);
        }

        $data = $operation['data'] ?? null;

        if (is_array($data) && isset($data['type'], $data['id'])) {
            $model = $this->newModel($data['type']);

            return $this->findModel($model, (string) $data['id']);
        }

        throw $this->validationError("/atomic:operations/{$index}", 'La operación requiere "ref" o "data" con "id".');
    }

    // Resuelve un ref (type + id | type + lid); rechaza `href`.
    protected function resolveAtomicRefModel(array $ref, int $index, array &$lidMap): Model
    {
        if (isset($ref['href'])) {
            throw $this->validationError("/atomic:operations/{$index}/ref/href", 'El miembro "href" en "ref" no está soportado; use "type"+"id" o "type"+"lid".', 400, 'unsupported_ref_href');
        }

        $type = $ref['type'] ?? null;

        if ($type === null) { throw $this->validationError("/atomic:operations/{$index}/ref/type", '"ref" debe incluir "type".'); }

        if (isset($ref['lid'])) {
            $instance = $lidMap[$ref['lid']] ?? null;

            if ($instance === null) { throw $this->validationError("/atomic:operations/{$index}/ref/lid", "No existe una operación previa con lid '{$ref['lid']}'."); }
            if ($this->resourceTypeForModel($instance) !== $type) { throw $this->validationError("/atomic:operations/{$index}/ref/type", 'El tipo no coincide con el recurso referenciado.'); }

            return $instance;
        }

        if (isset($ref['id'])) {
            $model = $this->newModel($type);

            return $this->findModel($model, (string) $ref['id']);
        }

        throw $this->validationError("/atomic:operations/{$index}/ref", '"ref" debe incluir "id" o "lid".');
    }

    // Sincroniza relaciones resolviendo lids (específico de atomic: lleva índice de operación para pointers).
    protected function syncRelationshipsWithLid(Model $model, array $relationships, array $lidMap, int $index): void
    {
        foreach ($relationships as $name => $relationship) {
            if (! isset($relationship['data'])) { continue; }

            $this->getRelationOrFail($model, $name);
            $resolved = $this->resolveAtomicLinkage($relationship['data'], $lidMap, $index);
            $this->syncRelationship($model, $name, $resolved, true);
        }
    }

    // Resuelve resource linkage sustituyendo lids por ids (versión atomic con índice).
    protected function resolveAtomicLinkage(mixed $data, array $lidMap, int $index): mixed
    {
        if ($data === null) { return null; }

        if (! is_array($data)) { throw $this->validationError("/atomic:operations/{$index}/data", '"data" debe ser un identificador, un array o null.'); }

        if ($this->isList($data)) {
            $out = [];

            foreach ($data as $i => $identifier) { $out[] = $this->resolveAtomicIdentifier($identifier, $lidMap, $index, $i); }

            return $out;
        }

        return $this->resolveAtomicIdentifier($data, $lidMap, $index, null);
    }

    // Resuelve un identificador individual (type + id | type + lid).
    protected function resolveAtomicIdentifier(array $identifier, array $lidMap, int $index, ?int $position): array
    {
        $suffix = $position === null ? '' : "/{$position}";
        $type = $identifier['type'] ?? null;

        if ($type === null) { throw $this->validationError("/atomic:operations/{$index}/data{$suffix}/type", 'El identificador debe incluir "type".'); }

        if (isset($identifier['lid'])) {
            $instance = $lidMap[$identifier['lid']] ?? null;

            if ($instance === null) { throw $this->validationError("/atomic:operations/{$index}/data{$suffix}/lid", "No existe una operación previa con lid '{$identifier['lid']}'."); }
            if ($this->resourceTypeForModel($instance) !== $type) { throw $this->validationError("/atomic:operations/{$index}/data{$suffix}/type", 'El tipo no coincide con el recurso referenciado.'); }

            return ['type' => $type, 'id' => (string) $instance->getKey()];
        }

        if (! isset($identifier['id'])) { throw $this->validationError("/atomic:operations/{$index}/data{$suffix}", 'El identificador debe incluir "id" o "lid".'); }

        return ['type' => $type, 'id' => (string) $identifier['id']];
    }
}