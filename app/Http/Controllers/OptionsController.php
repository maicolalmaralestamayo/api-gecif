<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OptionsController extends ErrorController
{
    // OPTIONS /{resource}
    public function optionsCollection(Request $request, string $resource)
    {
        return $this->optionsResponse($request, ['GET', 'HEAD', 'POST', 'OPTIONS'], $resource);
    }

    // OPTIONS /{resource}/{id}
    public function optionsResource(Request $request, string $resource, string $id)
    {
        return $this->optionsResponse($request, ['GET', 'HEAD', 'PATCH', 'PUT', 'DELETE', 'OPTIONS'], $resource);
    }

    // OPTIONS /{resource}/{id}/relationships/{related}
    public function optionsRelationship(Request $request, string $resource, string $id, string $related)
    {
        return $this->optionsResponse($request, ['GET', 'HEAD', 'PATCH', 'POST', 'DELETE', 'OPTIONS'], $resource);
    }

    // OPTIONS /{resource}/{id}/{related}
    public function optionsRelated(Request $request, string $resource, string $id, string $related)
    {
        return $this->optionsResponse($request, ['GET', 'HEAD', 'OPTIONS'], $resource);
    }

    // Documento de descubrimiento con Allow + extensiones/perfiles soportados.
    protected function optionsResponse(Request $request, array $allow, string $resource)
    {
        $payload = [
            'jsonapi' => $this->jsonApiObject(),
            'meta' => array_merge($this->requestMeta($request), [
                'allow' => array_values($allow),
                'resource' => $resource,
                'supported_extensions' => $this->supportedExtensions(),
                'supported_profiles' => $this->supportedProfiles(),
            ]),
            'links' => ['self' => $request->fullUrl()],
        ];

        return response()->json($payload, 200, [
            'Content-Type' => $this->contentType,
            'Allow' => implode(', ', $allow),
            'Vary' => 'Accept',
        ], $this->jsonEncodeFlags);
    }
}