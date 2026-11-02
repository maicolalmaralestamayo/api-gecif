<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DocsController extends DataController
{
    // Versión de OpenAPI generada.
    protected string $openApiVersion = '3.0.3';

    // Título de la API (desde APP_NAME).
    protected string $apiTitle = '';

    // Descripción de la API.
    protected string $apiDescription = 'Documentación auto-generada a partir de los modelos de la aplicación.';

    public function __construct()
    {
        parent::__construct();
        $this->apiTitle = (string) env('APP_NAME', 'API JSON:API');
    }

    // GET /docs/routes — lista todas las rutas registradas (diagnóstico).
    public function listRoutes(Request $request)
    {
        $output = [];

        foreach (app()->router->getRoutes() as $method => $uris) {
            foreach ($uris as $uri => $route) {
                $output[] = strtoupper((string) $method) . ' /' . $uri;
            }
        }

        sort($output);

        return response()->json(['routes' => $output, 'count' => count($output)], 200, ['Content-Type' => 'application/json'], $this->jsonEncodeFlags);
    }

    // GET /docs/openapi.json — descarga el spec como archivo .json.
    public function openApiDownload(Request $request)
    {
        $filename = 'openapi-' . date('Ymd-His') . '.json';

        return response()->json($this->buildOpenApiSpec($request), 200, ['Content-Type' => 'application/json', 'Content-Disposition' => 'attachment; filename="' . $filename . '"'], $this->jsonEncodeFlags);
    }

    // GET /docs/openapi.inline.json — sirve el spec inline (Swagger UI, Postman, openapi-generator).
    public function openApiInline(Request $request)
    {
        return response()->json($this->buildOpenApiSpec($request), 200, ['Content-Type' => 'application/json'], $this->jsonEncodeFlags);
    }

    // Construye el documento OpenAPI completo.
    protected function buildOpenApiSpec(Request $request): array
    {
        $models = $this->discoverModels();

        return [
            'openapi' => $this->openApiVersion,
            'info' => [
                'title' => $this->apiTitle,
                'description' => $this->apiDescription,
                'version' => $this->apiVersion,
            ],
            'servers' => [
                ['url' => rtrim($request->root(), '/'), 'description' => 'Servidor actual'],
            ],
            'paths' => $this->buildPaths($models),
            'components' => $this->buildComponents($models),
        ];
    }

    // Descubre los modelos en app/Models y app/ por reflexión de directorio.
    protected function discoverModels(): array
    {
        $base = base_path();
        $dirs = [$base . '/app/Models', $base . '/app'];
        $found = [];

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) { continue; }

            foreach (glob($dir . '/*.php') as $file) {
                $basename = basename($file, '.php');
                $class = str_contains($dir, '/Models') ? "App\\Models\\{$basename}" : "App\\{$basename}";

                if (! class_exists($class)) { continue; }
                if (! is_subclass_of($class, Model::class)) { continue; }

                try { $instance = new $class(); }
                catch (\Throwable $e) { continue; }

                $found[get_class($instance)] = $instance;
            }
        }

        ksort($found);

        return $found;
    }

    // Construye el mapa de paths del spec.
    protected function buildPaths(array $models): array
    {
        $paths = [];

        $paths['/'] = [
            'get' => [
                'summary' => 'Versión de la aplicación',
                'operationId' => 'root',
                'responses' => ['200' => ['description' => 'Versión en texto plano']],
            ],
        ];

        $paths['/operations'] = [
            'post' => [
                'summary' => 'Operaciones atómicas (JSON:API atomic:operations)',
                'operationId' => 'atomicOperations',
                'tags' => ['atomic'],
                'requestBody' => $this->jsonApiBody('AtomicOperationsRequest'),
                'responses' => [
                    '200' => $this->respJson('Resultados de las operaciones', 'AtomicOperationsResponse'),
                    '204' => ['description' => 'Todas las operaciones se completaron sin devolver datos'],
                    '400' => ['$ref' => '#/components/responses/BadRequest'],
                    '415' => ['$ref' => '#/components/responses/UnsupportedMediaType'],
                ],
            ],
        ];

        foreach ($models as $model) {
            $resource = $this->resourceTypeForModel($model);
            $suffix = Str::studly($resource);
            $relationships = $this->getRelationshipNames($model);

            $paths['/' . $resource] = [
                'get' => $this->opListCollection($resource, $suffix),
                'post' => $this->opCreateResource($resource, $suffix),
            ];

            $paths['/' . $resource . '/{id}'] = [
                'get' => $this->opGetResource($resource, $suffix),
                'patch' => $this->opUpdateResource($resource, $suffix),
                'put' => $this->opReplaceResource($resource, $suffix),
                'delete' => $this->opDeleteResource($resource, $suffix),
            ];

            $paths['/' . $resource . '/{id}/relationships/{related}'] = [
                'get' => $this->opRelationshipGet($resource, $suffix, $relationships),
                'patch' => $this->opRelationshipPatch($resource, $suffix, $relationships),
                'post' => $this->opRelationshipPost($resource, $suffix, $relationships),
                'delete' => $this->opRelationshipDelete($resource, $suffix, $relationships),
            ];

            $paths['/' . $resource . '/{id}/{related}'] = [
                'get' => $this->opRelatedGet($resource, $suffix, $relationships),
            ];
        }

        return $paths;
    }

    // GET colección.
    protected function opListCollection(string $resource, string $suffix): array
    {
        return [
            'summary' => "Listar '{$resource}' (colección)",
            'operationId' => "list{$suffix}",
            'tags' => [$resource],
            'parameters' => $this->commonQueryParams(),
            'responses' => [
                '200' => $this->respJson("Colección de '{$resource}'", "{$suffix}CollectionDocument"),
                '400' => ['$ref' => '#/components/responses/BadRequest'],
                '404' => ['$ref' => '#/components/responses/NotFound'],
            ],
        ];
    }

    // POST crear recurso.
    protected function opCreateResource(string $resource, string $suffix): array
    {
        return [
            'summary' => "Crear '{$resource}'",
            'operationId' => "create{$suffix}",
            'tags' => [$resource],
            'requestBody' => $this->jsonApiBody("{$suffix}ResourceDocument"),
            'responses' => [
                '201' => $this->respJson("'{$resource}' creado", "{$suffix}ResourceDocument"),
                '400' => ['$ref' => '#/components/responses/BadRequest'],
                '409' => ['$ref' => '#/components/responses/Conflict'],
                '415' => ['$ref' => '#/components/responses/UnsupportedMediaType'],
            ],
        ];
    }

    // GET recurso por id.
    protected function opGetResource(string $resource, string $suffix): array
    {
        return [
            'summary' => "Obtener '{$resource}' por id",
            'operationId' => "get{$suffix}",
            'tags' => [$resource],
            'parameters' => [$this->paramIdPath(), ['$ref' => '#/components/parameters/IncludeParam'], ['$ref' => '#/components/parameters/FieldsParam']],
            'responses' => [
                '200' => $this->respJson("'{$resource}' individual", "{$suffix}ResourceDocument"),
                '404' => ['$ref' => '#/components/responses/NotFound'],
            ],
        ];
    }

    // PATCH actualizar parcialmente.
    protected function opUpdateResource(string $resource, string $suffix): array
    {
        return [
            'summary' => "Actualizar parcialmente '{$resource}' (PATCH)",
            'operationId' => "patch{$suffix}",
            'tags' => [$resource],
            'parameters' => [$this->paramIdPath(), ['$ref' => '#/components/parameters/IncludeParam'], ['$ref' => '#/components/parameters/FieldsParam']],
            'requestBody' => $this->jsonApiBody("{$suffix}ResourceDocument"),
            'responses' => [
                '200' => $this->respJson("'{$resource}' actualizado", "{$suffix}ResourceDocument"),
                '400' => ['$ref' => '#/components/responses/BadRequest'],
                '404' => ['$ref' => '#/components/responses/NotFound'],
                '409' => ['$ref' => '#/components/responses/Conflict'],
            ],
        ];
    }

    // PUT reemplazar.
    protected function opReplaceResource(string $resource, string $suffix): array
    {
        return [
            'summary' => "Reemplazar '{$resource}' (PUT)",
            'operationId' => "put{$suffix}",
            'tags' => [$resource],
            'parameters' => [$this->paramIdPath(), ['$ref' => '#/components/parameters/IncludeParam'], ['$ref' => '#/components/parameters/FieldsParam']],
            'requestBody' => $this->jsonApiBody("{$suffix}ResourceDocument"),
            'responses' => [
                '200' => $this->respJson("'{$resource}' reemplazado", "{$suffix}ResourceDocument"),
                '400' => ['$ref' => '#/components/responses/BadRequest'],
                '404' => ['$ref' => '#/components/responses/NotFound'],
            ],
        ];
    }

    // DELETE eliminar recurso.
    protected function opDeleteResource(string $resource, string $suffix): array
    {
        return [
            'summary' => "Eliminar '{$resource}'",
            'operationId' => "delete{$suffix}",
            'tags' => [$resource],
            'parameters' => [$this->paramIdPath()],
            'responses' => [
                '204' => ['description' => "'{$resource}' eliminado"],
                '404' => ['$ref' => '#/components/responses/NotFound'],
            ],
        ];
    }

    // GET linkage de una relación.
    protected function opRelationshipGet(string $resource, string $suffix, array $relationships): array
    {
        return [
            'summary' => "Obtener linkage de una relación de '{$resource}'",
            'operationId' => "get{$suffix}Relationship",
            'tags' => [$resource],
            'parameters' => [$this->paramIdPath(), $this->paramRelatedPath($relationships), ['$ref' => '#/components/parameters/IncludeParam'], ['$ref' => '#/components/parameters/FieldsParam']],
            'responses' => [
                '200' => $this->respJson('Resource linkage', 'ResourceIdentifierDocument'),
                '404' => ['$ref' => '#/components/responses/NotFound'],
            ],
        ];
    }

    // PATCH reemplazar relación.
    protected function opRelationshipPatch(string $resource, string $suffix, array $relationships): array
    {
        return [
            'summary' => "Reemplazar una relación de '{$resource}'",
            'operationId' => "patch{$suffix}Relationship",
            'tags' => [$resource],
            'parameters' => [$this->paramIdPath(), $this->paramRelatedPath($relationships)],
            'requestBody' => $this->jsonApiBody('ResourceIdentifierDocument'),
            'responses' => [
                '200' => $this->respJson('Resource linkage', 'ResourceIdentifierDocument'),
                '400' => ['$ref' => '#/components/responses/BadRequest'],
                '404' => ['$ref' => '#/components/responses/NotFound'],
            ],
        ];
    }

    // POST añadir a relación.
    protected function opRelationshipPost(string $resource, string $suffix, array $relationships): array
    {
        return [
            'summary' => "Añadir elementos a una relación de '{$resource}'",
            'operationId' => "post{$suffix}Relationship",
            'tags' => [$resource],
            'parameters' => [$this->paramIdPath(), $this->paramRelatedPath($relationships)],
            'requestBody' => $this->jsonApiBody('ResourceIdentifierDocument'),
            'responses' => [
                '200' => $this->respJson('Resource linkage', 'ResourceIdentifierDocument'),
                '400' => ['$ref' => '#/components/responses/BadRequest'],
                '404' => ['$ref' => '#/components/responses/NotFound'],
            ],
        ];
    }

    // DELETE quitar de relación.
    protected function opRelationshipDelete(string $resource, string $suffix, array $relationships): array
    {
        return [
            'summary' => "Quitar elementos de una relación de '{$resource}'",
            'operationId' => "delete{$suffix}Relationship",
            'tags' => [$resource],
            'parameters' => [$this->paramIdPath(), $this->paramRelatedPath($relationships)],
            'requestBody' => $this->jsonApiBody('ResourceIdentifierDocument'),
            'responses' => [
                '200' => $this->respJson('Resource linkage', 'ResourceIdentifierDocument'),
                '400' => ['$ref' => '#/components/responses/BadRequest'],
                '404' => ['$ref' => '#/components/responses/NotFound'],
            ],
        ];
    }

    // GET recurso relacionado completo.
    protected function opRelatedGet(string $resource, string $suffix, array $relationships): array
    {
        return [
            'summary' => "Obtener el recurso relacionado completo de '{$resource}'",
            'operationId' => "get{$suffix}Related",
            'tags' => [$resource],
            'parameters' => array_merge([$this->paramIdPath(), $this->paramRelatedPath($relationships)], $this->commonQueryParams()),
            'responses' => [
                '200' => $this->respJson('Recurso(s) relacionado(s)', 'JsonApiGenericDocument'),
                '404' => ['$ref' => '#/components/responses/NotFound'],
            ],
        ];
    }

    // Construye los componentes del spec (schemas, responses, parameters).
    protected function buildComponents(array $models): array
    {
        $schemas = $this->sharedSchemas();
        $schemas['JsonApiGenericDocument'] = [
            'type' => 'object',
            'required' => ['data'],
            'properties' => [
                'jsonapi' => ['$ref' => '#/components/schemas/JsonApiObject'],
                'data' => ['oneOf' => [
                    ['type' => 'object', 'additionalProperties' => true],
                    ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                ]],
                'included' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                'links' => ['$ref' => '#/components/schemas/Links'],
                'meta' => ['type' => 'object', 'additionalProperties' => true],
            ],
        ];

        foreach ($models as $model) {
            $type = $this->resourceTypeForModel($model);
            $suffix = Str::studly($type);
            $relationships = $this->getRelationshipNames($model);

            $schemas["{$suffix}Attributes"] = $this->attributesSchema($model);

            $schemas["{$suffix}Resource"] = [
                'type' => 'object',
                'required' => ['type', 'id'],
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => [$type]],
                    'id' => ['type' => 'string'],
                    'lid' => ['type' => 'string'],
                    'attributes' => ['$ref' => "#/components/schemas/{$suffix}Attributes"],
                    'relationships' => [
                        'type' => 'object',
                        'description' => 'Relaciones disponibles: ' . implode(', ', $relationships),
                        'additionalProperties' => ['$ref' => '#/components/schemas/Relationship'],
                    ],
                    'links' => ['$ref' => '#/components/schemas/Links'],
                    'meta' => ['type' => 'object', 'additionalProperties' => true],
                ],
            ];

            $schemas["{$suffix}ResourceDocument"] = [
                'type' => 'object',
                'required' => ['data'],
                'properties' => [
                    'jsonapi' => ['$ref' => '#/components/schemas/JsonApiObject'],
                    'data' => ['$ref' => "#/components/schemas/{$suffix}Resource"],
                    'included' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                    'links' => ['$ref' => '#/components/schemas/Links'],
                    'meta' => ['type' => 'object', 'additionalProperties' => true],
                ],
            ];

            $schemas["{$suffix}CollectionDocument"] = [
                'type' => 'object',
                'required' => ['data'],
                'properties' => [
                    'jsonapi' => ['$ref' => '#/components/schemas/JsonApiObject'],
                    'data' => ['type' => 'array', 'items' => ['$ref' => "#/components/schemas/{$suffix}Resource"]],
                    'included' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                    'links' => ['$ref' => '#/components/schemas/Links'],
                    'meta' => ['type' => 'object', 'additionalProperties' => true],
                ],
            ];
        }

        return ['schemas' => $schemas, 'responses' => $this->sharedResponses(), 'parameters' => $this->sharedParameters()];
    }

    // Schemas compartidos de JSON:API.
    protected function sharedSchemas(): array
    {
        return [
            'JsonApiObject' => [
                'type' => 'object',
                'required' => ['version'],
                'properties' => [
                    'version' => ['type' => 'string', 'enum' => ['1.1']],
                    'ext' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'profile' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'meta' => ['type' => 'object', 'additionalProperties' => true],
                ],
            ],
            'Links' => [
                'type' => 'object',
                'properties' => [
                    'self' => ['type' => 'string'],
                    'related' => ['type' => 'string'],
                    'first' => ['type' => 'string'],
                    'last' => ['type' => 'string'],
                    'prev' => ['type' => 'string'],
                    'next' => ['type' => 'string'],
                    'describedby' => ['type' => 'string'],
                ],
                'additionalProperties' => true,
            ],
            'ResourceIdentifier' => [
                'type' => 'object',
                'required' => ['type', 'id'],
                'properties' => [
                    'type' => ['type' => 'string'],
                    'id' => ['type' => 'string'],
                    'lid' => ['type' => 'string'],
                ],
            ],
            'Relationship' => [
                'type' => 'object',
                'properties' => [
                    'links' => ['$ref' => '#/components/schemas/Links'],
                    'data' => ['oneOf' => [
                        ['$ref' => '#/components/schemas/ResourceIdentifier'],
                        ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ResourceIdentifier']],
                        ['type' => 'null'],
                    ]],
                    'meta' => ['type' => 'object', 'additionalProperties' => true],
                ],
            ],
            'ResourceIdentifierDocument' => [
                'type' => 'object',
                'required' => ['data'],
                'properties' => [
                    'jsonapi' => ['$ref' => '#/components/schemas/JsonApiObject'],
                    'data' => ['oneOf' => [
                        ['$ref' => '#/components/schemas/ResourceIdentifier'],
                        ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ResourceIdentifier']],
                        ['type' => 'null'],
                    ]],
                    'meta' => ['type' => 'object', 'additionalProperties' => true],
                ],
            ],
            'ErrorObject' => [
                'type' => 'object',
                'required' => ['id', 'status', 'code', 'title'],
                'properties' => [
                    'id' => ['type' => 'string'],
                    'status' => ['type' => 'string'],
                    'code' => ['type' => 'string'],
                    'title' => ['type' => 'string'],
                    'detail' => ['type' => 'string'],
                    'source' => [
                        'type' => 'object',
                        'properties' => [
                            'pointer' => ['type' => 'string'],
                            'parameter' => ['type' => 'string'],
                            'header' => ['type' => 'string'],
                        ],
                    ],
                    'links' => ['$ref' => '#/components/schemas/Links'],
                    'meta' => ['type' => 'object', 'additionalProperties' => true],
                ],
            ],
            'ErrorDocument' => [
                'type' => 'object',
                'required' => ['jsonapi', 'errors'],
                'properties' => [
                    'jsonapi' => ['$ref' => '#/components/schemas/JsonApiObject'],
                    'errors' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/ErrorObject']],
                    'links' => ['$ref' => '#/components/schemas/Links'],
                    'meta' => ['type' => 'object', 'additionalProperties' => true],
                ],
            ],
            'AtomicOperationsRequest' => [
                'type' => 'object',
                'required' => ['atomic:operations'],
                'properties' => [
                    'atomic:operations' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'required' => ['op'],
                            'properties' => [
                                'op' => ['type' => 'string', 'enum' => ['add', 'update', 'replace', 'remove']],
                                'ref' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'type' => ['type' => 'string'],
                                        'id' => ['type' => 'string'],
                                        'lid' => ['type' => 'string'],
                                        'relationship' => ['type' => 'string'],
                                    ],
                                ],
                                'data' => ['oneOf' => [
                                    ['type' => 'object', 'additionalProperties' => true],
                                    ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                                    ['type' => 'null'],
                                ]],
                            ],
                        ],
                    ],
                ],
            ],
            'AtomicOperationsResponse' => [
                'type' => 'object',
                'required' => ['atomic:results'],
                'properties' => [
                    'jsonapi' => ['$ref' => '#/components/schemas/JsonApiObject'],
                    'atomic:results' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                    'links' => ['$ref' => '#/components/schemas/Links'],
                    'meta' => ['type' => 'object', 'additionalProperties' => true],
                ],
            ],
        ];
    }

    // Responses compartidas.
    protected function sharedResponses(): array
    {
        $error = ['content' => ['application/vnd.api+json' => ['schema' => ['$ref' => '#/components/schemas/ErrorDocument']]]];

        return [
            'BadRequest' => ['description' => 'Petición inválida'] + $error,
            'NotFound' => ['description' => 'Recurso no encontrado'] + $error,
            'Conflict' => ['description' => 'Conflicto'] + $error,
            'UnsupportedMediaType' => ['description' => 'Content-Type no soportado'] + $error,
        ];
    }

    // Parameters compartidos (sintaxis JSON:API con style/explode correctos).
    protected function sharedParameters(): array
    {
        return [
            'IncludeParam' => [
                'name' => 'include',
                'in' => 'query',
                'description' => 'Relaciones a incluir (coma-separadas, anidamiento con "."). Ej: include=articulos,articulos.autor',
                'style' => 'form',
                'explode' => false,
                'schema' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'FieldsParam' => [
                'name' => 'fields',
                'in' => 'query',
                'description' => 'Sparse fieldsets: fields[TIPO]=campo1,campo2 (acepta "-campo" para exclusión).',
                'style' => 'deepObject',
                'explode' => true,
                'allowReserved' => true,
                'schema' => ['type' => 'object', 'additionalProperties' => ['type' => 'string']],
            ],
            'SortParam' => [
                'name' => 'sort',
                'in' => 'query',
                'description' => 'Ordenación (-prefijo para descendente). Ej: sort=-nombre,id',
                'schema' => ['type' => 'string'],
            ],
            'FilterParam' => [
                'name' => 'filter',
                'in' => 'query',
                'description' => 'Filtros: filter[campo]=valor, filter[campo][op]=valor, filter[or][N][campo]=valor.',
                'style' => 'deepObject',
                'explode' => true,
                'allowReserved' => true,
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'oneOf' => [
                            ['type' => 'string'],
                            ['type' => 'array', 'items' => ['type' => 'string']],
                            ['type' => 'object', 'additionalProperties' => ['type' => 'string']],
                        ],
                    ],
                ],
            ],
            'PageNumberParam' => [
                'name' => 'page[number]',
                'in' => 'query',
                'description' => 'Número de página (paginación offset).',
                'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            ],
            'PageSizeParam' => [
                'name' => 'page[size]',
                'in' => 'query',
                'description' => 'Tamaño de página (paginación offset).',
                'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 15],
            ],
            'PageAfterParam' => [
                'name' => 'page[after]',
                'in' => 'query',
                'description' => 'Cursor "after" (perfil de paginación por cursor).',
                'schema' => ['type' => 'string'],
            ],
            'PageBeforeParam' => [
                'name' => 'page[before]',
                'in' => 'query',
                'description' => 'Cursor "before" (perfil de paginación por cursor).',
                'schema' => ['type' => 'string'],
            ],
        ];
    }

    // Esquema de atributos derivado del modelo.
    protected function attributesSchema(Model $model): array
    {
        $fillable = $model->getFillable();
        $pk = $model->getKeyName();
        $candidates = $fillable !== [] ? $fillable : $this->safeColumnListing($model);
        $casts = $model->getCasts();
        $properties = [];

        foreach ($candidates as $attr) {
            if ($attr === $pk) { continue; }
            $properties[$attr] = $this->attrSchemaFor($casts[$attr] ?? null);
        }

        if ($properties === []) { return ['type' => 'object', 'additionalProperties' => true]; }

        return ['type' => 'object', 'properties' => $properties, 'additionalProperties' => false];
    }

    // Columnas del modelo (con fallback vacío si no hay DB).
    protected function safeColumnListing(Model $model): array
    {
        try { return $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable()); }
        catch (\Throwable $e) { return []; }
    }

    // Tipo OpenAPI a partir del cast de Eloquent.
    protected function attrSchemaFor(?string $cast): array
    {
        if ($cast === null) { return ['type' => 'string', 'nullable' => true]; }

        return match (true) {
            in_array($cast, ['int', 'integer', 'bigint', 'smallint', 'tinyint'], true) => ['type' => 'integer', 'nullable' => true],
            in_array($cast, ['bool', 'boolean'], true) => ['type' => 'boolean', 'nullable' => true],
            in_array($cast, ['float', 'double', 'decimal'], true) => ['type' => 'number', 'nullable' => true],
            in_array($cast, ['array', 'json', 'object', 'collection'], true) => ['type' => 'object', 'nullable' => true],
            in_array($cast, ['date', 'datetime', 'timestamp', 'immutable_date', 'immutable_datetime'], true) => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            str_starts_with($cast, 'decimal:') => ['type' => 'number', 'nullable' => true],
            default => ['type' => 'string', 'nullable' => true],
        };
    }

    // Respuesta con content application/vnd.api+json y $ref al schema indicado.
    protected function respJson(string $description, string $schemaRef): array
    {
        return ['description' => $description, 'content' => ['application/vnd.api+json' => ['schema' => ['$ref' => "#/components/schemas/{$schemaRef}"]]]];
    }

    // Body JSON:API con $ref al schema indicado.
    protected function jsonApiBody(string $schemaRef): array
    {
        return ['required' => true, 'content' => ['application/vnd.api+json' => ['schema' => ['$ref' => "#/components/schemas/{$schemaRef}"]]]];
    }

    // Parámetro path {id}.
    protected function paramIdPath(): array
    {
        return ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']];
    }

    // Parámetro path {related} con enum de relaciones disponibles.
    protected function paramRelatedPath(array $relationshipNames): array
    {
        $schema = ['type' => 'string'];

        if (! empty($relationshipNames)) { $schema['enum'] = $relationshipNames; }

        return [
            'name' => 'related',
            'in' => 'path',
            'required' => true,
            'schema' => $schema,
            'description' => 'Nombre de la relación. Valores: ' . (empty($relationshipNames) ? '(ninguno)' : implode(', ', $relationshipNames)),
        ];
    }

    // Query params comunes a colecciones y relaciones to-many.
    protected function commonQueryParams(): array
    {
        return [
            ['$ref' => '#/components/parameters/IncludeParam'],
            ['$ref' => '#/components/parameters/FieldsParam'],
            ['$ref' => '#/components/parameters/SortParam'],
            ['$ref' => '#/components/parameters/FilterParam'],
            ['$ref' => '#/components/parameters/PageNumberParam'],
            ['$ref' => '#/components/parameters/PageSizeParam'],
            ['$ref' => '#/components/parameters/PageAfterParam'],
            ['$ref' => '#/components/parameters/PageBeforeParam'],
        ];
    }
}