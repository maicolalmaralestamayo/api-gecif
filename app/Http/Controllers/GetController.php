<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GetController extends DataController
{
    // GET /{resource}
    public function getCollection(Request $request, string $resource)
    {
        if ($response = $this->validateAcceptHeader($request)) { return $response; }

        return $this->guard($request, function () use ($request, $resource) {
            $this->assertUnknownQueryParams($request, ['include', 'fields', 'sort', 'filter', 'page', 'relationships']);

            $model = $this->newModel($resource);
            [$includePaths, $includedParams, $fields, $relSorts] = $this->parseDocumentParams($request, $model);
            [$items, $total, $page, $size] = $this->applyQueryOptions($model->newQuery(), $request);
            $this->loadCollectionIncludes($items, $includePaths, $includedParams);
            [$data, $included] = $this->serializeData($items, $request, $includePaths, $fields, $relSorts);
            [$links, $meta] = $this->paginationBundle($request, $total, $page, $size);

            return $this->jsonApi($request, $data, 200, $included, $links, $meta, $fields);
        });
    }

    // GET /{resource}/{id}
    public function getObject(Request $request, string $resource, string $id)
    {
        if ($response = $this->validateAcceptHeader($request)) { return $response; }

        return $this->guard($request, function () use ($request, $resource, $id) {
            $this->assertUnknownQueryParams($request, ['include', 'fields', 'relationships']);

            $model = $this->newModel($resource);
            [$includePaths, $includedParams, $fields, $relSorts] = $this->parseDocumentParams($request, $model);
            $item = $this->findModel($model, $id);
            $incMeta = $this->loadCollectionIncludes(collect([$item]), $includePaths, $includedParams);
            [$data, $included] = $this->serializeData($item, $request, $includePaths, $fields, $relSorts);
            $meta = [];

            if (! empty($incMeta)) { $meta['included'] = $incMeta; }

            return $this->jsonApi($request, $data, 200, $included, ['self' => $this->linkValue($request->fullUrl(), [], 'Self')], $meta, $fields);
        });
    }

    // GET /{resource}/{id}/relationships/{related}
    public function getSmallRelatedResource(Request $request, string $resource, string $id, string $related)
    {
        if ($response = $this->validateAcceptHeader($request)) { return $response; }

        return $this->guard($request, function () use ($request, $resource, $id, $related) {
            $this->assertUnknownQueryParams($request, ['include', 'fields', 'sort', 'filter', 'page', 'relationships']);

            $parent = $this->findModel($this->newModel($resource), $id);
            $relation = $this->getRelationOrFail($parent, $related);
            $relatedModel = $relation->getRelated();

            [$includePaths, $includedParams, $fields] = $this->parseDocumentParams($request, $relatedModel);
            $baseLinks = $this->relationshipLinks($request, $parent, $related);

            if ($this->isToMany($relation)) {
                [$items, $total, $page, $size] = $this->applyQueryOptions($relation->getQuery(), $request);
                $this->loadCollectionIncludes($items, $includePaths, $includedParams);
                $excludeKeys = [];

                foreach ($items as $item) { $excludeKeys[$this->resourceKey($item)] = true; }

                $included = $this->collectIncluded($items, $includePaths, $request, $fields, $excludeKeys, []);
                [$paginationLinks, $meta] = $this->paginationBundle($request, $total, $page, $size);
                $links = array_merge($baseLinks, $paginationLinks);

                return $this->jsonApi($request, $this->linkage($items), 200, $included, $links, $meta, $fields);
            }

            $item = $relation->getResults();

            if (! $item) { return $this->jsonApi($request, null, 200, [], $baseLinks, [], $fields); }

            $incMeta = $this->loadCollectionIncludes(collect([$item]), $includePaths, $includedParams);
            $excludeKeys = [$this->resourceKey($item) => true];
            $included = $this->collectIncluded(collect([$item]), $includePaths, $request, $fields, $excludeKeys, []);
            $meta = [];

            if (! empty($incMeta)) { $meta['included'] = $incMeta; }

            return $this->jsonApi($request, $this->linkage($item), 200, $included, $baseLinks, $meta, $fields);
        });
    }

    // GET /{resource}/{id}/{related} (recurso relacionado completo)
    public function getFullRelatedResource(Request $request, string $resource, string $id, string $related)
    {
        if ($response = $this->validateAcceptHeader($request)) { return $response; }

        return $this->guard($request, function () use ($request, $resource, $id, $related) {
            $this->assertUnknownQueryParams($request, ['include', 'fields', 'sort', 'filter', 'page', 'relationships']);

            $parent = $this->findModel($this->newModel($resource), $id);
            $relation = $this->getRelationOrFail($parent, $related);
            $relatedModel = $relation->getRelated();

            [$includePaths, $includedParams, $fields, $relSorts] = $this->parseDocumentParams($request, $relatedModel);
            $baseLinks = $this->relationshipLinks($request, $parent, $related);

            if ($this->isToMany($relation)) {
                [$items, $total, $page, $size] = $this->applyQueryOptions($relation->getQuery(), $request);
                $this->loadCollectionIncludes($items, $includePaths, $includedParams);
                [$data, $included] = $this->serializeData($items, $request, $includePaths, $fields, $relSorts);
                [$paginationLinks, $meta] = $this->paginationBundle($request, $total, $page, $size);
                $links = array_merge($baseLinks, $paginationLinks);

                return $this->jsonApi($request, $data, 200, $included, $links, $meta, $fields);
            }

            $item = $relation->getResults();

            if (! $item) { return $this->jsonApi($request, null, 200, [], $baseLinks, [], $fields); }

            $incMeta = $this->loadCollectionIncludes(collect([$item]), $includePaths, $includedParams);
            [$data, $included] = $this->serializeData($item, $request, $includePaths, $fields, $relSorts);
            $meta = [];

            if (! empty($incMeta)) { $meta['included'] = $incMeta; }

            return $this->jsonApi($request, $data, 200, $included, $baseLinks, $meta, $fields);
        });
    }
}