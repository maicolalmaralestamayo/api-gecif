<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

abstract class WriteController extends DataController
{
    // Decodifica el cuerpo JSON (rechaza claves duplicadas, propaga @-members conocidos).
    protected function parseRequestBody(Request $request): array
    {
        $content = $request->getContent();

        if ($content === '' || $content === null) { throw $this->validationError('/', 'El cuerpo de la petición está vacío.'); }

        $this->assertNoDuplicateJsonKeys($content);

        $body = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) { throw $this->validationError('/', 'El cuerpo de la petición no es JSON válido.'); }
        if (! is_array($body)) { throw $this->validationError('/', 'El cuerpo de la petición debe ser un objeto JSON.'); }

        $this->recordKnownMembers($body);

        return $body;
    }

    // Valida `data` (obligatorio), `data.type` y `data.id` (en PATCH/PUT).
    protected function validateResourceData(array $body, string $expectedType, ?string $expectedId): array
    {
        if (! isset($body['data']) || ! is_array($body['data'])) { throw $this->validationError('/data', 'El cuerpo debe contener un objeto "data".'); }

        $data = $body['data'];

        if (! isset($data['type']) || $data['type'] !== $expectedType) { throw $this->validationError('/data/type', "El tipo del recurso debe ser '{$expectedType}'."); }

        if ($expectedId !== null) {
            if (! isset($data['id']) || (string) $data['id'] !== $expectedId) { throw $this->validationError('/data/id', 'El id del recurso no coincide con el de la URL.'); }
        }

        return $data;
    }

    // Valida `data` según to-many (array) o to-one (identifier|null).
    protected function validateRelationshipData(array $body, Relation $relation): mixed
    {
        if (! isset($body['data'])) { throw $this->validationError('/data', 'El cuerpo debe contener el miembro "data".'); }

        $data = $body['data'];

        if ($this->isToMany($relation)) {
            if (! is_array($data) || ! $this->isList($data)) { throw $this->validationError('/data', 'Para relaciones a-muchos, "data" debe ser un array de identificadores.'); }

            foreach ($data as $i => $identifier) {
                if (! isset($identifier['type'], $identifier['id'])) { throw $this->validationError("/data/{$i}", 'Cada identificador debe tener "type" e "id".'); }
            }
        } else {
            if ($data !== null && (! is_array($data) || ! isset($data['type'], $data['id']))) { throw $this->validationError('/data', 'Para relaciones a-uno, "data" debe ser un identificador o null.'); }
        }

        return $data;
    }

    // Extrae atributos filtrando contra columnas reales (multi-error si hay desconocidos).
    protected function extractAttributes(array $data, Model $model): array
    {
        $attributes = $data['attributes'] ?? [];
        unset($attributes[$model->getKeyName()], $attributes['type']);

        $columns = $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable());
        $unknown = array_diff(array_keys($attributes), $columns);

        foreach ($unknown as $field) {
            $this->addValidationError('/data/attributes/' . $field, "El atributo '{$field}' no existe en '{$model->getTable()}'.", 400, 'invalid_attribute');
        }

        if (! empty($unknown)) { throw new \InvalidArgumentException('Uno o más atributos no son válidos.'); }

        return array_intersect_key($attributes, array_flip($columns));
    }

    // Extrae relaciones del recurso.
    protected function extractRelationships(array $data): array
    {
        return $data['relationships'] ?? [];
    }

    // Sincroniza todas las relaciones.
    protected function syncRelationships(Model $model, array $relationships): void
    {
        foreach ($relationships as $name => $relationship) {
            if (! isset($relationship['data'])) { continue; }

            $this->getRelationOrFail($model, $name);
            $this->syncRelationship($model, $name, $relationship['data'], true);
        }
    }

    // Sincroniza una relación individual según su tipo concreto.
    protected function syncRelationship(Model $model, string $name, mixed $data, bool $replace = true, bool $detach = false): void
    {
        $relation = $model->{$name}();

        if ($relation instanceof BelongsTo) { $this->syncBelongsTo($relation, $model, $data); return; }
        if ($relation instanceof BelongsToMany) { $this->syncBelongsToMany($relation, $data, $replace, $detach); return; }
        if ($relation instanceof HasMany || $relation instanceof MorphMany) { $this->syncHasMany($relation, $model, $data, $replace, $detach); return; }
        if ($relation instanceof HasOne || $relation instanceof MorphOne) { $this->syncHasOne($relation, $model, $data); return; }

        throw $this->validationError('/data/relationships', "Tipo de relación '{$name}' no soportado para sincronización.");
    }

    // BelongsTo: associate / dissociate.
    protected function syncBelongsTo(BelongsTo $relation, Model $model, mixed $data): void
    {
        if ($data === null) { $relation->dissociate(); }
        else {
            $this->resolveRelatedModel($relation, $data['type']);
            $relation->associate($data['id']);
        }

        $model->save();
    }

    // BelongsToMany: sync / syncWithoutDetaching / detach.
    protected function syncBelongsToMany(BelongsToMany $relation, mixed $data, bool $replace, bool $detach): void
    {
        $ids = [];

        foreach ($data as $identifier) {
            $this->resolveRelatedModel($relation, $identifier['type']);
            $ids[] = $identifier['id'];
        }

        if ($detach) { $relation->detach($ids); }
        elseif ($replace) { $relation->sync($ids); }
        else { $relation->syncWithoutDetaching($ids); }
    }

    // HasMany: actualiza el FK de los hijos.
    protected function syncHasMany(HasMany|MorphMany $relation, Model $parent, array $data, bool $replace, bool $detach): void
    {
        $relatedClass = get_class($relation->getRelated());
        $relatedKey = $relation->getRelated()->getKeyName();
        $foreignKey = $relation->getForeignKeyName();
        $parentKey = $parent->getKey();

        $ids = [];

        foreach ($data as $identifier) {
            $this->resolveRelatedModel($relation, $identifier['type']);
            $ids[] = $identifier['id'];
        }

        $detachUpdate = [$foreignKey => null];
        $attachUpdate = [$foreignKey => $parentKey];

        if ($relation instanceof MorphMany) {
            $morphType = $relation->getMorphType();
            $detachUpdate[$morphType] = null;
            $attachUpdate[$morphType] = $parent->getMorphClass();
        }

        if ($detach) {
            if (! empty($ids)) { $relatedClass::query()->whereIn($relatedKey, $ids)->where($foreignKey, $parentKey)->update($detachUpdate); }
            return;
        }

        if ($replace) {
            $current = $relatedClass::query()->where($foreignKey, $parentKey)->pluck($relatedKey)->all();
            $orphans = array_diff($current, $ids);

            if (! empty($orphans)) { $relatedClass::query()->whereIn($relatedKey, $orphans)->update($detachUpdate); }
        }

        if (! empty($ids)) { $relatedClass::query()->whereIn($relatedKey, $ids)->update($attachUpdate); }
    }

    // HasOne: reasigna el FK del hijo único.
    protected function syncHasOne(HasOne|MorphOne $relation, Model $parent, mixed $data): void
    {
        $relatedClass = get_class($relation->getRelated());
        $relatedKey = $relation->getRelated()->getKeyName();
        $foreignKey = $relation->getForeignKeyName();
        $parentKey = $parent->getKey();

        $detachUpdate = [$foreignKey => null];

        if ($relation instanceof MorphOne) { $detachUpdate[$relation->getMorphType()] = null; }

        $relatedClass::query()->where($foreignKey, $parentKey)->update($detachUpdate);

        if ($data === null) { return; }

        $this->resolveRelatedModel($relation, $data['type']);

        $attachUpdate = [$foreignKey => $parentKey];

        if ($relation instanceof MorphOne) { $attachUpdate[$relation->getMorphType()] = $parent->getMorphClass(); }

        $relatedClass::query()->where($relatedKey, $data['id'])->update($attachUpdate);
    }

    // Resuelve el modelo relacionado a partir de su tipo JSON:API.
    protected function resolveRelatedModel(Relation $relation, string $type): Model
    {
        $relatedClass = get_class($relation->getRelated());
        $model = new $relatedClass();

        if ($this->resourceTypeForModel($model) !== $type) { throw $this->validationError('/data', "El tipo '{$type}' no coincide con el recurso relacionado."); }

        return $model;
    }

    // Aplica reemplazo completo (PUT): atributos enviados, relaciones no enviadas se limpian.
    protected function applyFullReplacement(Model $instance, array $data): void
    {
        $model = $instance->newInstance();

        $attributes = $this->extractAttributes($data, $model);

        if (! empty($attributes)) {
            $instance->fill($attributes);
            $instance->save();
        }

        $providedRelationships = $data['relationships'] ?? [];

        foreach ($this->getRelationshipNames($instance) as $name) {
            $relation = $instance->{$name}();
            $emptyValue = $this->isToMany($relation) ? [] : null;
            $relData = isset($providedRelationships[$name]['data']) ? $providedRelationships[$name]['data'] : $emptyValue;
            $this->syncRelationship($instance, $name, $relData, true);
        }
    }

    // Rechaza `included` en PATCH/PUT.
    protected function rejectIncludedOnUpdate(array $body): void
    {
        if (isset($body['included']) && is_array($body['included']) && $body['included'] !== []) {
            throw $this->validationError('/included', 'El miembro "included" no está permitido en esta operación. Use POST /operations (atomic:operations) para crear recursos relacionados en una sola petición.', 400, 'included_not_allowed');
        }
    }

    // Recoge todos los lids (data + included) y valida formato + unicidad global.
    protected function collectAndValidateLids(array $body): array
    {
        $lids = [];

        $data = $body['data'] ?? null;

        if (is_array($data) && isset($data['lid'])) { $this->registerLid($lids, $data['lid'], '/data/lid'); }

        $included = $body['included'] ?? [];

        if (is_array($included)) {
            foreach ($included as $i => $resource) {
                if (is_array($resource) && isset($resource['lid'])) { $this->registerLid($lids, $resource['lid'], "/included/{$i}/lid"); }
            }
        }

        return $lids;
    }

    // Registra un lid en el mapa global validando formato y unicidad.
    protected function registerLid(array &$lids, mixed $lid, string $pointer): void
    {
        if (! is_string($lid) || $lid === '') { throw $this->validationError($pointer, 'El lid debe ser un string no vacío.', 400, 'invalid_lid'); }
        if (strlen($lid) > 255) { throw $this->validationError($pointer, 'El lid no puede superar 255 caracteres.', 400, 'invalid_lid'); }
        if (isset($lids[$lid])) { throw $this->validationError($pointer, "El lid '{$lid}' está duplicado en el documento.", 400, 'duplicate_lid'); }

        $lids[$lid] = true;
    }
}