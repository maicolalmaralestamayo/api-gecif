<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class JsonApiResource extends JsonResource
{
    public function toArray($request)
    {
        $relationships = $this->resource::$jsonApiRelationships;
        $relations = [];
        foreach ($relationships as $relationship => $type) {
            switch ($type) {
                case 'HasOne':
                    $relations[$relationship] = $this->$relationship;
                    break;

                case 'BelongsTo':
                    $relations[$relationship] = $this->$relationship;
                    break;
                
                default:
                    # code...
                    break;
            }
        }

        return [
            'id' => (string) $this->id,
            'type' => $this->resource::$jsonApiType,
            'attributes' => Arr::except($this->getAttributes(), 'id'),
            'relationships' => $relations,
        ];
    }
}