<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PutController extends WriteController
{
    // PUT /{resource}/{id} — reemplazo completo, soporta include y fields en la respuesta.
    public function replaceResource(Request $request, string $resource, string $id)
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

            $lid = isset($data['lid']) && is_string($data['lid']) ? $data['lid'] : null;

            $instance = $this->transaction(function () use ($model, $id, $data) {
                $instance = $this->findModel($model, $id);
                $this->applyFullReplacement($instance, $data);
                return $instance;
            });

            $this->loadCollectionIncludes(collect([$instance]), $includePaths, $includedParams);

            [$resourceData, $included] = $this->serializeData($instance, $request, $includePaths, $fields, $relSorts);

            if ($lid !== null) { $resourceData['lid'] = $lid; }

            return $this->jsonApi($request, $resourceData, 200, $included, ['self' => $this->linkValue($this->selfUrl($request, $instance), [], 'Self')], [], $fields);
        });
    }
}