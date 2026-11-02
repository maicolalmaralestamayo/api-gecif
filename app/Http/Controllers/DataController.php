<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// Variables .env opcionales:
//   JSONAPI_DEFAULT_PAGE_SIZE   → page[size] por defecto (default 15)
//   JSONAPI_MAX_PAGE_SIZE       → page[size] máximo (default 100)
//   JSONAPI_APP_URL             → base de URLs para links (default APP_URL o $request->root())
//   JSONAPI_RESOURCE_MAP        → JSON mapa recurso→modelo (default vacío → convención App\Models\*)

abstract class DataController extends ErrorController
{
    // Tamaño de página por defecto y máximo.
    protected int $defaultPageSize = 15;
    protected int $maxPageSize = 100;

    // Base de URLs.
    protected string $appUrl = '';

    // Mapa de secuencias codificadas a decodificar en URLs.
    protected array $urlDecodeMap = ['%5B' => '[', '%5D' => ']', '%2C' => ',', '%2F' => '/', '%3A' => ':'];

    // Operadores válidos.
    protected array $filterOperators = [
        'eq', 'ne', 'gt', 'gte', 'lt', 'lte',
        'like', 'ilike', 'in', 'notin', 'between',
        'is_null', 'is_not_null',
        'starts_with', 'ends_with', 'contains',
        'year', 'month', 'day', 'date', 'time',
        'json_contains', 'json_length',
        'fulltext',
    ];

    // Mapa opcional recurso→modelo.
    protected array $resourceMap = [];

    // Cachés.
    protected array $relationshipCache = [];
    protected array $typeCache = [];

    // Origen de cada recurso incluido (para full linkage).
    protected array $includedOrigins = [];

    // Estado de paginación por cursor.
    protected bool $cursorMode = false;
    protected array $cursorSort = [];
    protected int $cursorSize = 0;
    protected array $cursorLinks = [];
    protected array $cursorMeta = [];

    public function __construct()
    {
        parent::__construct();
        $this->defaultPageSize = (int) env('JSONAPI_DEFAULT_PAGE_SIZE', 15);
        $this->maxPageSize = (int) env('JSONAPI_MAX_PAGE_SIZE', 100);
        $this->appUrl = rtrim((string) env('JSONAPI_APP_URL', env('APP_URL', '')), '/');
        $this->resourceMap = $this->decodeJsonEnv('JSONAPI_RESOURCE_MAP', []);
    }

    // Rechaza parámetros de query desconocidos (fail-fast).
    protected function assertUnknownQueryParams(Request $request, array $allowed): void
    {
        foreach (array_keys($request->query()) as $key) {
            if (! in_array($key, $allowed, true)) {
                $this->addValidationError('/?' . $key, "Parámetro de query no soportado: '{$key}'.", 400, 'invalid_query_parameter');
            }
        }

        if ($this->hasValidationErrors()) { throw new \InvalidArgumentException('Uno o más parámetros de query no son válidos.'); }
    }

    // Resuelve el modelo por convención.
    protected function newModel(string $resource): Model
    {
        $class = $this->resolveModelClass($resource);
        $model = new $class();

        if (! $model instanceof Model) { throw new \InvalidArgumentException("El recurso '{$resource}' no es un modelo Eloquent válido."); }

        return $model;
    }

    // Traduce el recurso URL a su clase de modelo.
    protected function resolveModelClass(string $resource): string
    {
        $key = strtolower(trim($resource));

        if (isset($this->resourceMap[$key]) && class_exists($this->resourceMap[$key])) { return $this->resourceMap[$key]; }

        $clean = preg_replace('/[^a-z0-9_\-]/', '', $key);
        $studly = Str::studly($clean);

        if ($studly === '') { throw new \InvalidArgumentException("El recurso '{$resource}' no es válido."); }

        foreach (["App\\Models\\{$studly}", "App\\{$studly}"] as $class) {
            if (class_exists($class)) { return $class; }
        }

        throw new \InvalidArgumentException("El recurso '{$resource}' no está registrado en el controlador.");
    }

    // Busca un modelo por id o lanza 404.
    protected function findModel(Model $model, string $id): Model
    {
        $found = $model->newQuery()->find($id);

        if (! $found) { throw new \Illuminate\Database\Eloquent\ModelNotFoundException(); }

        return $found;
    }

    // Tipo JSON:API del modelo (snake_case minúsculo).
    protected function resourceTypeForModel(Model $model): string
    {
        $class = get_class($model);

        if (isset($this->typeCache[$class])) { return $this->typeCache[$class]; }

        foreach ($this->resourceMap as $type => $mappedClass) {
            if ($class === $mappedClass) { return $this->typeCache[$class] = $type; }
        }

        return $this->typeCache[$class] = Str::snake(class_basename($class));
    }

    // Descubre relaciones Eloquent por reflexión.
    protected function getRelationshipNames(Model $model): array
    {
        $class = get_class($model);

        if (isset($this->relationshipCache[$class])) { return $this->relationshipCache[$class]; }

        $names = [];
        $reflection = new \ReflectionClass($model);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->getNumberOfRequiredParameters() > 0) { continue; }

            $name = $method->getName();

            if (str_starts_with($name, '__')) { continue; }

            $declaring = $method->getDeclaringClass()->getName();

            if (Str::startsWith($declaring, 'Illuminate\\') || Str::startsWith($declaring, 'Laravel\\')) { continue; }

            try { if ($model->{$name}() instanceof Relation) { $names[] = $name; } } catch (\Throwable $e) { /* No es una relación. */ }
        }

        return $this->relationshipCache[$class] = $names;
    }

    // Determina si la relación es a-muchos.
    protected function isToMany(Relation $relation): bool
    {
        return ! ($relation instanceof BelongsTo || $relation instanceof HasOne || $relation instanceof HasOneThrough || $relation instanceof MorphOne);
    }

    // Devuelve la relación o lanza 400 si no existe.
    protected function getRelationOrFail(Model $model, string $name): Relation
    {
        if (! in_array($name, $this->getRelationshipNames($model), true)) { throw new \InvalidArgumentException("La relación '{$name}' no existe en '{$this->resourceTypeForModel($model)}'."); }

        $relation = $model->{$name}();

        if (! $relation instanceof Relation) { throw new \InvalidArgumentException("El método '{$name}' no devuelve una relación Eloquent."); }

        return $relation;
    }

    // Valida que cada segmento de `include` sea una relación real (fail-fast).
    protected function validateIncludes(Model $model, array $includePaths): void
    {
        foreach ($includePaths as $path) {
            $currentModel = $model;

            foreach (explode('.', $path) as $segment) {
                if (! in_array($segment, $this->getRelationshipNames($currentModel), true)) {
                    $this->addValidationError('/include', "La relación '{$segment}' no es válida para '{$this->resourceTypeForModel($currentModel)}'.", 400, 'invalid_include');
                    continue 2;
                }

                $relation = $currentModel->{$segment}();

                if (! $relation instanceof Relation) {
                    $this->addValidationError('/include', "El método '{$segment}' no devuelve una relación Eloquent.", 400, 'invalid_include');
                    continue 2;
                }

                $currentModel = $relation->getRelated();
            }
        }

        if ($this->hasValidationErrors()) { throw new \InvalidArgumentException('Uno o más valores de "include" no son válidos.'); }
    }

    // Valida que los campos de sparse fieldsets existan como atributo o relación (fail-fast).
    protected function validateSparseFieldNames(Model $model, array $fields, array $includePaths): void
    {
        if ($fields === []) { return; }

        $types = [$this->resourceTypeForModel($model) => $model];

        foreach ($includePaths as $path) {
            $current = $model;

            foreach (explode('.', $path) as $segment) {
                $relation = $current->{$segment}();
                $current = $relation->getRelated();
                $type = $this->resourceTypeForModel($current);
                if (! isset($types[$type])) { $types[$type] = $current; }
            }
        }

        foreach ($fields as $type => $fieldList) {
            if (! isset($types[$type])) { continue; }

            $m = $types[$type];
            $columns = $m->getConnection()->getSchemaBuilder()->getColumnListing($m->getTable());
            $relationships = $this->getRelationshipNames($m);
            $valid = array_merge($columns, $relationships);

            foreach ($fieldList as $field) {
                $bare = (is_string($field) && $field !== '' && $field[0] === '-') ? substr($field, 1) : $field;
                if ($bare === '') { continue; }
                if (! in_array($bare, $valid, true)) {
                    $this->addValidationError('/fields/' . $type, "El campo '{$bare}' no existe en el recurso '{$type}'.", 400, 'invalid_sparse_field');
                }
            }
        }

        if ($this->hasValidationErrors()) { throw new \InvalidArgumentException('Uno o más campos de sparse fieldsets no son válidos.'); }
    }

    // Construye un resource object JSON:API.
    protected function modelToResource(Model $model, Request $request, array $sparseFields = [], array $relSorts = [], bool $withCursor = false, ?string $lid = null): array
    {
        $type = $this->resourceTypeForModel($model);
        $sparseSpec = $sparseFields[$type] ?? null;
        $attributes = $model->attributesToArray();
        unset($attributes[$model->getKeyName()]);

        if ($sparseSpec !== null) { $attributes = $this->applySparseFields($attributes, $sparseSpec); }

        $resource = ['type' => $type, 'id' => (string) $model->getKey(), 'attributes' => (object) $attributes];
        $relationships = [];

        foreach ($this->getRelationshipNames($model) as $name) {
            if (! $model->relationLoaded($name)) { $model->loadMissing($name); }

            $relationship = ['links' => $this->relationshipLinks($request, $model, $name)];
            $loaded = $model->getRelation($name);

            if ($loaded instanceof Collection) {
                $relationship['meta'] = ['count' => $loaded->count()];
                $sort = $relSorts[$name] ?? null;

                if ($sort !== null) { $loaded = $loaded->sortBy(fn (Model $i) => $i->getKey(), SORT_REGULAR, str_starts_with($sort, '-'))->values(); }
            } elseif ($loaded instanceof Model) {
                $relationship['meta'] = ['count' => 1];
            } else {
                $relationship['meta'] = ['count' => 0];
            }

            $relationship['data'] = $this->linkage($loaded);
            $relationships[$name] = $relationship;
        }

        if ($relationships !== []) { $resource['relationships'] = $relationships; }

        $resourceMeta = $this->resourceMeta($model);

        if ($resourceMeta !== []) { $resource['meta'] = $resourceMeta; }

        if ($withCursor && $this->cursorMode && $this->cursorSort !== []) {
            $resource['meta'] = array_merge($resource['meta'] ?? [], ['page' => ['cursor' => $this->encodeCursor($this->cursorSort, $model)]]);
        }

        $resource['links'] = ['self' => $this->selfLink($request, $model)];

        if ($this->resourceRelatedUrl !== '') {
            $url = str_replace(['{type}', '{id}'], [$type, $this->encodedId($model)], $this->resourceRelatedUrl);
            $resource['links']['related'] = $this->linkValue($url, [], 'Related resources');
        }

        if ($this->resourceDescribedByUrl !== '') {
            $url = str_replace(['{type}', '{id}'], [$type, $this->encodedId($model)], $this->resourceDescribedByUrl);
            $resource['links']['describedby'] = $this->linkValue($url, [], 'Resource schema');
        }

        if ($lid !== null) { $resource['lid'] = $lid; }

        return $resource;
    }

    // Resource identifier {type, id}.
    protected function identifier(Model $model): array
    {
        return ['type' => $this->resourceTypeForModel($model), 'id' => (string) $model->getKey()];
    }

    // Resource linkage a partir de Model, Collection o null.
    protected function linkage(Model|Collection|null $value): ?array
    {
        if ($value instanceof Collection) { return $value->map(fn (Model $item) => $this->identifier($item))->values()->all(); }
        if ($value instanceof Model) { return $this->identifier($value); }

        return null;
    }

    // Clave única (type:id).
    protected function resourceKey(Model $model): string
    {
        return $this->resourceTypeForModel($model) . ':' . $model->getKey();
    }

    // Meta por recurso: created y revision.
    protected function resourceMeta(Model $model): array
    {
        $meta = [];

        if (($created = $model->getAttribute('created_at')) !== null) {
            $meta['created'] = $created instanceof \DateTimeInterface ? $created->format($this->timestampFormat) : (string) $created;
        }

        if (($updated = $model->getAttribute('updated_at')) !== null) {
            $meta['revision'] = $updated instanceof \DateTimeInterface ? $updated->format($this->timestampFormat) : (string) $updated;
        }

        return $meta;
    }

    // URLs canónicas de una relación.
    protected function relationshipUrls(Request $request, Model $model, string $related): array
    {
        $base = $this->baseUrl($request);
        $type = $this->resourceTypeForModel($model);
        $id = $this->encodedId($model);

        return [
            'self' => "{$base}/{$type}/{$id}/relationships/{$related}",
            'related' => "{$base}/{$type}/{$id}/{$related}",
        ];
    }

    // Links `self`, `related` y `describedby` de una relación.
    protected function relationshipLinks(Request $request, Model $model, string $related): array
    {
        $urls = $this->relationshipUrls($request, $model, $related);

        $links = [
            'self' => $this->linkValue($urls['self'], [], 'Relationship self'),
            'related' => $this->linkValue($urls['related'], [], 'Related resource'),
        ];

        if ($this->relationshipDescribedByUrl !== '') {
            $url = str_replace(['{type}', '{id}', '{related}'], [$this->resourceTypeForModel($model), $this->encodedId($model), $related], $this->relationshipDescribedByUrl);
            $links['describedby'] = $this->linkValue($url, [], 'Relationship schema');
        }

        return $links;
    }

    // URL canónica de un recurso.
    protected function selfUrl(Request $request, Model $model): string
    {
        return $this->baseUrl($request) . '/' . $this->resourceTypeForModel($model) . '/' . $this->encodedId($model);
    }

    // Link `self` de un recurso.
    protected function selfLink(Request $request, Model $model): array|string
    {
        return $this->linkValue($this->selfUrl($request, $model), [], 'Self');
    }

    // Base de URLs.
    protected function baseUrl(Request $request): string
    {
        return $this->appUrl !== '' ? $this->appUrl : rtrim($request->root(), '/');
    }

    // ID del modelo URL-encoded.
    protected function encodedId(Model $model): string
    {
        return rawurlencode((string) $model->getKey());
    }

    // Serializa Model o Collection a [$data, $included].
    protected function serializeData(Model|Collection $models, Request $request, array $includePaths, array $fields, array $relSorts = []): array
    {
        $isSingle = $models instanceof Model;
        $collection = $isSingle ? collect([$models]) : $models;

        $data = $isSingle
            ? $this->modelToResource($models, $request, $fields, $relSorts, false)
            : $collection->map(fn (Model $item) => $this->modelToResource($item, $request, $fields, $relSorts, $this->cursorMode))->values()->all();

        $excludeKeys = [];

        foreach ($collection as $item) { $excludeKeys[$this->resourceKey($item)] = true; }

        return [$data, $this->collectIncluded($collection, $includePaths, $request, $fields, $excludeKeys, $relSorts)];
    }

    // Recorre `include` y construye `included` sin duplicados.
    protected function collectIncluded(Collection $models, array $includePaths, Request $request, array $fields, array $excludeKeys = [], array $relSorts = []): array
    {
        $this->includedOrigins = [];
        $included = [];

        foreach ($includePaths as $path) {
            $segments = explode('.', $path);

            foreach ($models as $model) { if ($model instanceof Model) { $this->collectIncludedFromModel($model, $segments, $request, $fields, $included, $excludeKeys, $relSorts); } }
        }

        return $included;
    }

    // Recolecta recursivamente los recursos incluidos.
    protected function collectIncludedFromModel(Model $model, array $segments, Request $request, array $fields, array &$included, array $excludeKeys, array $relSorts = []): void
    {
        if ($segments === []) { return; }

        $relationName = array_shift($segments);

        if (! $model->relationLoaded($relationName)) { return; }

        $relation = $model->getRelation($relationName);

        if ($relation instanceof Collection) { $relatedModels = $relation; }
        elseif ($relation instanceof Model) { $relatedModels = collect([$relation]); }
        else { $relatedModels = collect(); }

        foreach ($relatedModels as $relatedModel) {
            if (! $relatedModel instanceof Model) { continue; }

            $key = $this->resourceKey($relatedModel);

            if (! isset($excludeKeys[$key]) && ! isset($included[$key])) {
                $included[$key] = $this->modelToResource($relatedModel, $request, $fields, $relSorts);
                $this->includedOrigins[$key] = [
                    'parentType' => $this->resourceTypeForModel($model),
                    'relation' => $relationName,
                ];
            }

            if ($segments !== []) { $this->collectIncludedFromModel($relatedModel, $segments, $request, $fields, $included, $excludeKeys, $relSorts); }
        }
    }

    // Verifica full linkage de cada recurso en `included`; devuelve JsonResponse si falla.
    protected function assertFullLinkage(Request $request, ?array $data, array $included, array $sparseFields): ?JsonResponse
    {
        if ($included === []) { return null; }

        $linkIndex = [];

        $collect = function (array $resource) use (&$linkIndex) {
            $rels = $resource['relationships'] ?? [];

            foreach ($rels as $rel) {
                $d = $rel['data'] ?? null;

                if ($d === null) { continue; }

                if (is_array($d) && isset($d['type'], $d['id']) && ! $this->isList($d)) {
                    $linkIndex[$d['type'] . ':' . $d['id']] = true;
                } elseif (is_array($d)) {
                    foreach ($d as $identifier) {
                        if (is_array($identifier) && isset($identifier['type'], $identifier['id'])) { $linkIndex[$identifier['type'] . ':' . $identifier['id']] = true; }
                    }
                }
            }
        };

        $iter = $data === null ? [] : ($this->isList($data) ? $data : [$data]);

        foreach ($iter as $r) { if (is_array($r)) { $collect($r); } }
        foreach ($included as $r) { if (is_array($r)) { $collect($r); } }

        foreach ($included as $resource) {
            $key = ($resource['type'] ?? '') . ':' . ($resource['id'] ?? '');

            if (isset($linkIndex[$key])) { continue; }

            $origin = $this->includedOrigins[$key] ?? null;

            if ($origin !== null) {
                $parentFields = $sparseFields[$origin['parentType']] ?? null;

                if ($parentFields !== null && ! in_array($origin['relation'], $parentFields, true)) { continue; }
            }

            return $this->errorResponse($request, 500, 'Internal Server Error', "Full linkage falló: el recurso '{$key}' en included no está referenciado por ninguna relación.");
        }

        return null;
    }

    // Parsea y valida los parámetros de documento.
    protected function parseDocumentParams(Request $request, Model $model): array
    {
        $includePaths = $this->parseInclude($request);
        $includedParams = $this->parseIncludedParams($request);
        $this->validateIncludes($model, $includePaths);
        $fields = $this->sparseFields($request);
        $this->validateSparseFieldNames($model, $fields, $includePaths);

        return [$includePaths, $includedParams, $fields, $this->relationshipSorts($includedParams)];
    }

    // Parsea `?include=a,b.c`.
    protected function parseInclude(Request $request): array
    {
        $include = $request->query('include');

        if (! is_string($include) || trim($include) === '') { return []; }

        return array_values(array_unique(array_filter(array_map('trim', explode(',', $include)))));
    }

    // Parsea filter/sort/page/relationships por relación.
    protected function parseIncludedParams(Request $request): array
    {
        $params = [];
        $filters = $request->query('filter');

        if (is_array($filters)) {
            foreach ($filters as $rel => $value) {
                if (! is_string($rel) || ! is_array($value) || $rel === 'or' || str_contains($rel, '.') || isset($value['count']) || $this->isList($value) || $this->isOperatorArray($value)) { continue; }
                $params[$rel]['filter'] = $value;
            }
        }

        $sorts = $request->query('sort');

        if (is_array($sorts)) { foreach ($sorts as $rel => $value) { if (is_string($rel) && is_string($value)) { $params[$rel]['sort'] = $value; } } }

        $pages = $request->query('page');

        if (is_array($pages)) {
            foreach ($pages as $rel => $value) {
                if (! is_string($rel) || ! is_array($value)) { continue; }
                if (in_array($rel, ['number', 'size'], true)) { continue; }
                $params[$rel]['page'] = $value;
            }
        }

        $rels = $request->query('relationships');

        if (is_array($rels)) {
            foreach ($rels as $rel => $cfg) {
                if (! is_string($rel) || ! is_array($cfg) || ! isset($cfg['sort'])) { continue; }

                $sort = (string) $cfg['sort'];
                $clean = ltrim($sort, '-');

                if ($clean !== 'id') { $this->addValidationError('/relationships/' . $rel . '/sort', "El parámetro 'relationships[{$rel}][sort]' solo admite 'id' o '-id'.", 400, 'invalid_relationship_sort'); }

                $params[$rel]['relationships_sort'] = $sort;
            }
        }

        if ($this->hasValidationErrors()) { throw new \InvalidArgumentException('Uno o más parámetros por relación no son válidos.'); }

        return $params;
    }

    // Extrae los `relationships[rel][sort]`.
    protected function relationshipSorts(array $includedParams): array
    {
        $sorts = [];

        foreach ($includedParams as $rel => $cfg) {
            if (isset($cfg['relationships_sort'])) { $sorts[$rel] = $cfg['relationships_sort']; }
        }

        return $sorts;
    }

    // Parsea `?fields[type]=a,b`.
    protected function sparseFields(Request $request): array
    {
        $fields = $request->query('fields');

        if (! is_array($fields)) { return []; }

        $result = [];

        foreach ($fields as $type => $value) {
            if (is_string($value)) { $value = explode(',', $value); }
            if (is_array($value)) { $result[$type] = array_values(array_filter(array_map('trim', $value), fn ($v) => $v !== '')); }
        }

        return $result;
    }

    // Aplica sparse fields.
    protected function applySparseFields(array $attributes, array $spec): array
    {
        $exclude = false;
        $fields = [];

        foreach ($spec as $field) {
            $field = (string) $field;

            if ($field === '') { continue; }
            if ($field[0] === '-') { $exclude = true; $field = substr($field, 1); }
            if ($field !== '') { $fields[] = $field; }
        }

        if ($fields === []) { return $attributes; }
        if ($exclude) { return array_diff_key($attributes, array_flip($fields)); }

        return array_intersect_key($attributes, array_flip($fields));
    }

    // Aplica filtros + (cursor u offset) paginación.
    protected function applyQueryOptions(Builder $query, Request $request): array
    {
        $this->applyFilters($query, $request);

        if ($this->isCursorMode($request)) { return $this->applyCursorQueryOptions($query, $request); }

        $this->applySort($query, $request);

        if (! $request->query('sort')) { $query->orderBy($query->getModel()->getQualifiedKeyName()); }

        [$page, $size] = $this->pagination($request);
        $total = (clone $query)->count();
        $items = $query->forPage($page, $size)->get();

        return [$items, $total, $page, $size];
    }

    // ¿Usa paginación por cursor?
    protected function isCursorMode(Request $request): bool
    {
        $page = $request->query('page', []);

        if (is_array($page) && (isset($page['after']) || isset($page['before']))) { return true; }

        if (in_array(self::CURSOR_PROFILE, $this->clientProfiles, true) && ! (is_array($page) && isset($page['number']))) { return true; }

        return false;
    }

    // Paginación por cursor.
    protected function applyCursorQueryOptions(Builder $query, Request $request): array
    {
        $model = $query->getModel();
        $spec = $this->normalizeSort($request, $model);

        $this->cursorMode = true;
        $this->cursorSort = $spec;
        $this->appliedProfiles[] = self::CURSOR_PROFILE;

        [$size, $after, $before] = $this->cursorParams($request);
        $this->cursorSize = $size;

        if ($after !== null && $before !== null) {
            throw $this->validationError('/page', 'La paginación por rango (page[after] y page[before] juntos) no está soportada.', 400, 'range_pagination_not_supported');
        }

        $valuesAfter = $after !== null ? $this->decodeCursor($after, 'page[after]', $spec) : null;
        $valuesBefore = $before !== null ? $this->decodeCursor($before, 'page[before]', $spec) : null;

        $querySpec = $before !== null ? $this->reverseSortSpec($spec) : $spec;
        $this->applySortSpec($query, $querySpec);

        if ($valuesAfter !== null) { $this->applyCursorPredicate($query, $spec, $valuesAfter, '>'); }
        if ($valuesBefore !== null) { $this->applyCursorPredicate($query, $spec, $valuesBefore, '<'); }

        $items = $query->limit($size + 1)->get();
        $hasExtra = $items->count() > $size;

        if ($hasExtra) { $items = $items->slice(0, $size)->values(); }
        if ($before !== null) { $items = $items->reverse()->values(); }

        $this->cursorLinks = $this->buildCursorLinks($request, $items, $after !== null, $before !== null, $hasExtra);
        $this->cursorMeta = ['page' => ['size' => $size]];

        return [$items, null, null, $size];
    }

    // Normaliza `sort` con PK como desempate.
    protected function normalizeSort(Request $request, Model $model): array
    {
        $sort = $request->query('sort');
        $spec = [];
        $seen = [];

        if (is_string($sort) && trim($sort) !== '') {
            foreach (explode(',', $sort) as $part) {
                $part = trim($part);

                if ($part === '') { continue; }

                $direction = 'asc';

                if ($part[0] === '-') { $direction = 'desc'; $part = substr($part, 1); }

                $this->assertValidSortField($model, $part);

                $column = $this->qualifyColumn($model, $part);

                if (isset($seen[$column])) { continue; }

                $seen[$column] = true;
                $spec[] = ['column' => $column, 'direction' => $direction];
            }
        }

        $pk = $model->getQualifiedKeyName();

        if (! isset($seen[$pk])) { $spec[] = ['column' => $pk, 'direction' => 'asc']; }

        return $spec;
    }

    // Aplica una especificación de ordenación.
    protected function applySortSpec(Builder $query, array $spec): void
    {
        foreach ($spec as $s) { $query->orderBy($s['column'], $s['direction']); }
    }

    // Invierte direcciones de una especificación.
    protected function reverseSortSpec(array $spec): array
    {
        return array_map(fn ($s) => ['column' => $s['column'], 'direction' => $s['direction'] === 'asc' ? 'desc' : 'asc'], $spec);
    }

    // Extrae y valida page[size]/after/before.
    protected function cursorParams(Request $request): array
    {
        $page = $request->query('page', []);

        if (! is_array($page)) { $page = []; }

        $size = $this->cursorPageSize($page);
        $after = isset($page['after']) && is_string($page['after']) && $page['after'] !== '' ? $page['after'] : null;
        $before = isset($page['before']) && is_string($page['before']) && $page['before'] !== '' ? $page['before'] : null;

        return [$size, $after, $before];
    }

    // Valida page[size] según el perfil.
    protected function cursorPageSize(array $page): int
    {
        if (! isset($page['size'])) { return $this->defaultPageSize; }

        $raw = $page['size'];

        if (! is_numeric($raw) || (int) $raw <= 0) {
            throw $this->validationError('/page[size]', 'page[size] debe ser un entero positivo.', 400, 'invalid_query_parameter');
        }

        $size = (int) $raw;

        if ($size > $this->maxPageSize) {
            throw $this->validationError('/page[size]', "page[size] no puede superar {$this->maxPageSize}.", 400, 'max_page_size_exceeded');
        }

        return $size;
    }

    // Codifica un cursor opaco (base64url de JSON).
    protected function encodeCursor(array $spec, Model $model): string
    {
        $values = [];

        foreach ($spec as $s) {
            $column = $s['column'];
            $attr = strpos($column, '.') !== false ? substr($column, strrpos($column, '.') + 1) : $column;
            $value = $model->getAttribute($attr);

            if ($value instanceof \DateTimeInterface) { $value = $value->format('Y-m-d\TH:i:s.uP'); }
            if (is_array($value) || is_object($value)) { throw $this->validationError('/page', 'No se puede generar un cursor para una columna no escalar.', 400, 'invalid_cursor'); }

            $values[] = $value;
        }

        $json = json_encode(['v' => $values, 's' => $this->sortSignature($spec)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    // Decodifica y valida un cursor.
    protected function decodeCursor(string $cursor, string $param, array $spec): array
    {
        $normalized = strtr($cursor, '-_', '+/');
        $pad = strlen($normalized) % 4;

        if ($pad !== 0) { $normalized .= str_repeat('=', 4 - $pad); }

        $json = base64_decode($normalized, true);

        if ($json === false) { throw $this->validationError('/' . $param, 'Cursor inválido.', 400, 'invalid_cursor'); }

        $payload = json_decode($json, true);

        if (! is_array($payload) || ! isset($payload['v'], $payload['s']) || ! is_array($payload['v'])) {
            throw $this->validationError('/' . $param, 'Cursor inválido.', 400, 'invalid_cursor');
        }

        if ($payload['s'] !== $this->sortSignature($spec) || count($payload['v']) !== count($spec)) {
            throw $this->validationError('/' . $param, 'Cursor inválido para el orden solicitado.', 400, 'invalid_cursor');
        }

        return $payload['v'];
    }

    // Firma corta del orden (para validar cursores).
    protected function sortSignature(array $spec): string
    {
        return substr(md5(json_encode(array_map(fn ($s) => $s['column'] . ':' . $s['direction'], $spec))), 0, 12);
    }

    // Predicado keyset (lexicográfico).
    protected function applyCursorPredicate(Builder $query, array $spec, array $values, string $op): void
    {
        $count = count($spec);

        $query->where(function (Builder $group) use ($spec, $values, $op, $count) {
            for ($i = 0; $i < $count; $i++) {
                $group->orWhere(function (Builder $inner) use ($spec, $values, $op, $i) {
                    for ($j = 0; $j < $i; $j++) { $inner->where($spec[$j]['column'], '=', $values[$j]); }

                    $value = $values[$i];

                    if ($value === null) { throw $this->validationError('/page', 'No se soportan cursores con valores NULL.', 400, 'invalid_cursor'); }

                    $direction = $spec[$i]['direction'];
                    $comparison = $op === '>' ? ($direction === 'asc' ? '>' : '<') : ($direction === 'asc' ? '<' : '>');

                    $inner->where($spec[$i]['column'], $comparison, $value);
                });
            }
        });
    }

    // Construye links prev/next del cursor (con meta.cursor).
    protected function buildCursorLinks(Request $request, Collection $items, bool $hadAfter, bool $hadBefore, bool $hasExtra): array
    {
        $links = ['prev' => null, 'next' => null];

        if ($items->isEmpty()) { return $links; }

        $first = $items->first();
        $last = $items->last();

        if ($hadAfter) {
            if ($hasExtra) {
                $nextCursor = $this->encodeCursor($this->cursorSort, $last);
                $links['next'] = $this->linkValue($this->urlWithCursor($request, 'after', $nextCursor), ['cursor' => $nextCursor], 'Next page');
            }

            $prevCursor = $this->encodeCursor($this->cursorSort, $first);
            $links['prev'] = $this->linkValue($this->urlWithCursor($request, 'before', $prevCursor), ['cursor' => $prevCursor], 'Previous page');
        } elseif ($hadBefore) {
            $nextCursor = $this->encodeCursor($this->cursorSort, $last);
            $links['next'] = $this->linkValue($this->urlWithCursor($request, 'after', $nextCursor), ['cursor' => $nextCursor], 'Next page');

            if ($hasExtra) {
                $prevCursor = $this->encodeCursor($this->cursorSort, $first);
                $links['prev'] = $this->linkValue($this->urlWithCursor($request, 'before', $prevCursor), ['cursor' => $prevCursor], 'Previous page');
            }
        } else {
            if ($hasExtra) {
                $nextCursor = $this->encodeCursor($this->cursorSort, $last);
                $links['next'] = $this->linkValue($this->urlWithCursor($request, 'after', $nextCursor), ['cursor' => $nextCursor], 'Next page');
            }
        }

        return $links;
    }

    // URL actual sustituyendo el cursor.
    protected function urlWithCursor(Request $request, string $param, string $cursor): string
    {
        $query = $request->query();

        if (! is_array($query)) { $query = []; }

        $page = isset($query['page']) && is_array($query['page']) ? $query['page'] : [];

        unset($page['number'], $page['after'], $page['before']);

        $page[$param] = $cursor;
        $query['page'] = $page;

        $qs = http_build_query($query, '', '&');
        $qs = strtr($qs, $this->urlDecodeMap);

        return $request->url() . '?' . $qs;
    }

    // URL sin parámetros de paginación (canónica para self).
    protected function urlWithoutPage(Request $request): string
    {
        $query = $request->query();

        if (! is_array($query)) { $query = []; }

        unset($query['page']);

        if ($query === []) { return $request->url(); }

        $qs = http_build_query($query, '', '&');
        $qs = strtr($qs, $this->urlDecodeMap);

        return $request->url() . '?' . $qs;
    }

    // Devuelve [links, meta] de paginación (cursor u offset).
    protected function paginationBundle(Request $request, ?int $total, ?int $page, ?int $size): array
    {
        if ($this->cursorMode) { return [$this->cursorLinks, $this->cursorMeta]; }

        return [
            $this->paginationLinks($request, (int) $total, (int) $page, (int) $size),
            $this->paginationMeta((int) $total, (int) $page, (int) $size),
        ];
    }

    // Aplica filtros del request al query.
    protected function applyFilters(Builder $query, Request $request): void
    {
        $filters = $request->query('filter');

        if (! is_array($filters)) { return; }

        if (isset($filters['or']) && is_array($filters['or'])) {
            $this->applyOrFilters($query, $query->getModel(), $filters['or']);
            unset($filters['or']);
        }

        foreach ($filters as $field => $value) { $this->applySingleFilter($query, $query->getModel(), (string) $field, $value); }
    }

    // Aplica un grupo OR.
    protected function applyOrFilters(Builder $query, Model $model, array $orGroup): void
    {
        $query->where(function (Builder $outer) use ($model, $orGroup) {
            foreach ($orGroup as $subFilter) {
                if (! is_array($subFilter)) { continue; }

                $outer->orWhere(function (Builder $inner) use ($model, $subFilter) {
                    foreach ($subFilter as $field => $value) { $this->applySingleFilter($inner, $model, (string) $field, $value); }
                });
            }
        });
    }

    // Aplica un filtro individual.
    protected function applySingleFilter(Builder $query, Model $model, string $field, mixed $value): void
    {
        if ($field === '' || ! $this->isValidField($field)) { return; }

        if (str_contains($field, '.pivot.')) {
            [$relation, , $pivotColumn] = explode('.', $field, 3);
            $query->whereHas($relation, fn ($q) => $q->wherePivot($pivotColumn, $value));
            return;
        }

        if (str_contains($field, '.')) {
            [$prefix, $column] = explode('.', $field, 2);

            if (in_array($prefix, $this->getRelationshipNames($model), true)) {
                $related = $model->{$prefix}()->getRelated();
                $this->assertColumnExists($related, $column, '/filter', 'invalid_filter');

                $query->whereHas($prefix, function (Builder $q) use ($column, $value) { $this->applyColumnFilter($q, $column, $value); });
                return;
            }

            $this->applyColumnFilter($query, $field, $value);
            return;
        }

        if (in_array($field, $this->getRelationshipNames($model), true) && is_array($value) && count($value) === 1 && isset($value['count']) && is_array($value['count'])) {
            $this->applyAggregationFilter($query, $field, $value['count']);
            return;
        }

        $this->applyColumnFilter($query, $field, $value);
    }

    // Aplica filtro sobre una columna.
    protected function applyColumnFilter(Builder $query, string $field, mixed $value): void
    {
        $model = $query->getModel();

        $this->assertColumnExists($model, $field, '/filter', 'invalid_filter');

        $column = $this->qualifyColumn($model, $field);

        if (is_array($value)) {
            if ($this->isList($value)) { $query->whereIn($column, $value); return; }

            if ($this->isOperatorArray($value)) {
                foreach ($value as $op => $v) { $this->applyFilterOperator($query, $column, (string) $op, $v); }
                return;
            }

            return;
        }

        $query->where($column, $value);
    }

    // Comprueba que una columna exista realmente en el modelo.
    protected function assertColumnExists(Model $model, string $field, string $pointer, string $code): void
    {
        if (str_contains($field, '.')) { return; }

        $columns = $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable());

        if (! in_array($field, $columns, true)) {
            $this->addValidationError($pointer, "El campo '{$field}' no existe en '{$model->getTable()}'.", 400, $code);
        }

        if ($this->hasValidationErrors()) { throw new \InvalidArgumentException('Uno o más campos de filtro no son válidos.'); }
    }

    // Aplica agregación sobre relación.
    protected function applyAggregationFilter(Builder $query, string $relation, array $operators): void
    {
        $op = '>=';
        $count = 1;

        foreach ($operators as $operator => $val) {
            $op = match ($operator) {
                'eq' => '=', 'ne' => '!=', 'gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<=', default => '>=',
            };
            $count = (int) $val;
        }

        $query->has($relation, $op, $count);
    }

    // Aplica un operador de filtro.
    protected function applyFilterOperator(Builder $query, string $column, string $op, mixed $value): void
    {
        match ($op) {
            'eq' => $query->where($column, '=', $value),
            'ne' => $query->where($column, '!=', $value),
            'gt' => $query->where($column, '>', $value),
            'gte' => $query->where($column, '>=', $value),
            'lt' => $query->where($column, '<', $value),
            'lte' => $query->where($column, '<=', $value),
            'like' => $query->where($column, 'like', $value),
            'ilike' => $query->where($column, 'ilike', $value),
            'in' => $query->whereIn($column, is_array($value) ? $value : explode(',', (string) $value)),
            'notin' => $query->whereNotIn($column, is_array($value) ? $value : explode(',', (string) $value)),
            'between' => $query->whereBetween($column, is_array($value) ? array_values($value) : explode(',', (string) $value)),
            'is_null' => $this->isTruthy($value) ? $query->whereNull($column) : null,
            'is_not_null' => $this->isTruthy($value) ? $query->whereNotNull($column) : null,
            'starts_with' => $query->where($column, 'like', $value . '%'),
            'ends_with' => $query->where($column, 'like', '%' . $value),
            'contains' => $query->where($column, 'like', '%' . $value . '%'),
            'year' => $query->whereYear($column, '=', $value),
            'month' => $query->whereMonth($column, '=', $value),
            'day' => $query->whereDay($column, '=', $value),
            'date' => $query->whereDate($column, '=', $value),
            'time' => $query->whereTime($column, '=', $value),
            'json_contains' => $query->whereJsonContains($column, is_string($value) ? json_decode($value, true) : $value),
            'json_length' => $query->whereJsonLength($column, '=', (int) $value),
            'fulltext' => $query->whereFullText($column, (string) $value),
            default => null,
        };
    }

    // Comprueba si el array asociativo solo contiene operadores válidos.
    protected function isOperatorArray(array $value): bool
    {
        if ($value === [] || $this->isList($value)) { return false; }

        foreach (array_keys($value) as $key) {
            if (! in_array($key, $this->filterOperators, true)) { return false; }
        }

        return true;
    }

    // Convierte un valor a booleano.
    protected function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) { return $value; }
        if ($value === null || $value === '') { return false; }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    // Aplica `?sort=-campo,campo2` (offset).
    protected function applySort(Builder $query, Request $request): void
    {
        $sort = $request->query('sort');

        if (! is_string($sort) || trim($sort) === '') { return; }

        $this->applySortString($query, $query->getModel(), $sort);
    }

    // Aplica una cadena de sort.
    protected function applySortString(Builder $query, Model $model, string $sort): void
    {
        foreach (explode(',', $sort) as $part) {
            $part = trim($part);

            if ($part === '') { continue; }

            $direction = 'asc';

            if ($part[0] === '-') { $direction = 'desc'; $part = substr($part, 1); }

            $this->assertValidSortField($model, $part);

            $query->orderBy($this->qualifyColumn($model, $part), $direction);
        }
    }

    // Valida campo de sort (regex + existencia de columna).
    protected function assertValidSortField(Model $model, string $part): void
    {
        if (! $this->isValidField($part)) {
            $this->addValidationError('/sort', "Campo de ordenación no válido: {$part}.", 400, 'invalid_sort');
        } else {
            $this->assertColumnExists($model, $part, '/sort', 'invalid_sort');
        }

        if ($this->hasValidationErrors()) { throw new \InvalidArgumentException('Uno o más campos de ordenación no son válidos.'); }
    }

    // Valida un nombre de campo.
    protected function isValidField(string $name, bool $allowDot = true): bool
    {
        return $allowDot ? (bool) preg_match('/^[A-Za-z0-9_.]+$/', $name) : (bool) preg_match('/^[A-Za-z0-9_]+$/', $name);
    }

    // Califica la columna con la tabla del modelo.
    protected function qualifyColumn(Model $model, string $column): string
    {
        return strpos($column, '.') !== false ? $column : $model->getTable() . '.' . $column;
    }

    // Lee `page[number]` y `page[size]` (offset).
    protected function pagination(Request $request): array
    {
        $page = $request->query('page', []);

        if (! is_array($page)) { $page = []; }

        $number = max(1, (int) ($page['number'] ?? 1));
        $size = (int) ($page['size'] ?? $this->defaultPageSize);

        if ($size <= 0) { $size = $this->defaultPageSize; }

        $size = min($this->maxPageSize, $size);

        return [$number, $size];
    }

    // Meta de paginación offset.
    protected function paginationMeta(int $total, int $page, int $size): array
    {
        return [
            'total' => $total,
            'page' => [
                'number' => $page,
                'size' => $size,
                'total_pages' => max(1, (int) ceil($total / $size)),
            ],
        ];
    }

    // Links de paginación offset (self sin page; resto con meta.page).
    protected function paginationLinks(Request $request, int $total, int $page, int $size): array
    {
        $last = max(1, (int) ceil($total / $size));

        $links = [
            'self' => $this->linkValue($this->urlWithoutPage($request), ['total' => $total, 'page_size' => $size], 'Collection'),
            'first' => $this->linkValue($this->urlWithPage($request, 1, $size), ['page' => 1], 'First page'),
            'last' => $this->linkValue($this->urlWithPage($request, $last, $size), ['page' => $last], 'Last page'),
        ];

        if ($page > 1) { $links['prev'] = $this->linkValue($this->urlWithPage($request, $page - 1, $size), ['page' => $page - 1], 'Previous page'); }
        if ($page < $last) { $links['next'] = $this->linkValue($this->urlWithPage($request, $page + 1, $size), ['page' => $page + 1], 'Next page'); }

        return $links;
    }

    // URL con `page[number]` y `page[size]` sobrescritos.
    protected function urlWithPage(Request $request, int $page, int $size): string
    {
        $query = $request->query();

        if (! is_array($query)) { $query = []; }

        $query['page'] = ['number' => $page, 'size' => $size];
        $qs = http_build_query($query, '', '&');
        $qs = strtr($qs, $this->urlDecodeMap);

        return $request->url() . '?' . $qs;
    }

    // Carga los `include` en una colección.
    protected function loadCollectionIncludes(Collection $models, array $includePaths, array $includedParams): array
    {
        $incMeta = [];

        if ($includePaths !== []) {
            foreach ($models as $item) { $this->loadIncludes($item, $includePaths, $includedParams, $incMeta); }
        }

        return $incMeta;
    }

    // Carga los `include` con per-relation params.
    protected function loadIncludes(Model $model, array $includePaths, array $includedParams, array &$incMeta): void
    {
        $tree = $this->buildIncludeTree($includePaths);
        $this->loadIncludeTree($model, $tree, $includedParams, $incMeta);
    }

    // Convierte ['a', 'a.b'] en árbol.
    protected function buildIncludeTree(array $includePaths): array
    {
        $tree = [];

        foreach ($includePaths as $path) {
            $node = &$tree;

            foreach (explode('.', $path) as $seg) {
                if (! isset($node[$seg])) { $node[$seg] = []; }
                $node = &$node[$seg];
            }

            unset($node);
        }

        return $tree;
    }

    // Recorre el árbol de includes.
    protected function loadIncludeTree(Model $model, array $tree, array $includedParams, array &$incMeta): void
    {
        foreach ($tree as $relName => $children) {
            $relation = $model->{$relName}();
            $params = $includedParams[$relName] ?? [];

            if (! $this->isToMany($relation)) {
                $model->load($relName);
                $related = $model->getRelation($relName);

                if ($related instanceof Model && ! empty($children)) { $this->loadIncludeTree($related, $children, $includedParams, $incMeta); }

                continue;
            }

            $query = $relation->getQuery();
            $relatedModel = $query->getModel();

            foreach ($params['filter'] ?? [] as $field => $value) { $this->applySingleFilter($query, $relatedModel, (string) $field, $value); }

            if (! empty($params['sort'])) { $this->applySortString($query, $relatedModel, (string) $params['sort']); }
            else { $query->orderBy($relatedModel->getQualifiedKeyName()); }

            $pageNumber = max(1, (int) ($params['page']['number'] ?? 1));
            $pageSize = isset($params['page']['size']) ? min($this->maxPageSize, max(1, (int) $params['page']['size'])) : null;

            if ($pageSize !== null) {
                $total = (clone $query)->count();
                $query->forPage($pageNumber, $pageSize);
                $incMeta[$relName] = $this->paginationMeta($total, $pageNumber, $pageSize);
            }

            $items = $query->get();
            $model->setRelation($relName, $items);

            if (! empty($children)) { foreach ($items as $item) { $this->loadIncludeTree($item, $children, $includedParams, $incMeta); } }
        }
    }

    // Comprueba si un array es lista.
    protected function isList(array $arr): bool
    {
        $i = 0;

        foreach ($arr as $k => $_) { if ($k !== $i++) { return false; } }

        return true;
    }

    // Cuenta los objetos de data.
    protected function countData(?array $data): int
    {
        if ($data === null) { return 0; }
        if ($this->isList($data)) { return count($data); }

        return 1;
    }

    // Documento JSON:API de éxito (con full linkage, describedby y custom members).
    protected function jsonApi(Request $request, ?array $data, int $status = 200, array $included = [], array $links = [], array $meta = [], array $sparseFields = [])
    {
        if ($included !== []) {
            $linkageError = $this->assertFullLinkage($request, $data, $included, $sparseFields);

            if ($linkageError !== null) { return $linkageError; }
        }

        $payload = ['jsonapi' => $this->jsonApiObject(), 'data' => $data];

        if ($included !== []) { $payload['included'] = array_values($included); }

        $links = $this->documentLinks($links);

        if ($links !== []) { $payload['links'] = $links; }

        $counters = ['data' => ['count' => $this->countData($data)]];

        if ($included !== []) { $counters['included'] = ['count' => count($included)]; }

        $payload['meta'] = array_merge($this->requestMeta($request), $counters, $meta);

        $payload = $this->mergeCustomMembers($payload);

        return response()->json($payload, $status, ['Content-Type' => $this->responseContentType(), 'Vary' => 'Accept'], $this->jsonEncodeFlags);
    }
}