<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DeleteController extends WriteController
{
    // DELETE /{resource}/{id}
    public function deleteResource(Request $request, string $resource, string $id)
    {
        if ($response = $this->validateAcceptHeader($request)) { return $response; }

        return $this->guard($request, function () use ($resource, $id) {
            $model = $this->newModel($resource);
            $instance = $this->findModel($model, $id);

            $this->transaction(function () use ($instance) { $instance->delete(); });

            return response('', 204);
        });
    }

    // DELETE /{resource}/{id}/relationships/{related}
    public function removeFromRelationship(Request $request, string $resource, string $id, string $related)
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

            $this->transaction(fn () => $this->syncRelationship($instance, $related, $data, false, true));

            return $this->jsonApi($request, $this->linkage($instance->{$related}()->get()), 200, [], $this->relationshipLinks($request, $instance, $related));
        });
    }
}