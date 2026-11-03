<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Extranjero extends Model
{
    use SoftDeletes;

    protected $table = 'extranjeros';

    protected $fillable = [
        'trabajador_id',
        'nombres_apellidos',
        'parentesco_id',
        'pais_id',
        'mantiene_relacion',
    ];

    protected $casts = [
        'trabajador_id'     => 'integer',
        'parentesco_id'     => 'integer',
        'pais_id'           => 'integer',
        'mantiene_relacion' => 'boolean',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
        'deleted_at'        => 'datetime',
    ];

    // Trabajador al que pertenece el familiar extranjero
    public function trabajador()
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    // Nomenclador de parentesco
    public function parentesco()
    {
        return $this->belongsTo(Parentesco::class, 'parentesco_id');
    }

    // Nomenclador de país
    public function pais()
    {
        return $this->belongsTo(Pais::class, 'pais_id');
    }
}