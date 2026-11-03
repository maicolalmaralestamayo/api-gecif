<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Entidad extends Model
{
    use SoftDeletes;

    protected $table = 'entidads';

    protected $fillable = [
        'numero',
        'nombre',
        'abreviatura',
        'entidad_mayor',
        'observacion',
    ];

    protected $casts = [
        'entidad_mayor' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Entidad padre (0 = raíz, sin registro asociado)
    public function mayor()
    {
        return $this->belongsTo(Entidad::class, 'entidad_mayor');
    }

    // Entidades subordinadas directas
    public function subordinadas()
    {
        return $this->hasMany(Entidad::class, 'entidad_mayor');
    }

    // Plazas de la entidad
    public function plazas()
    {
        return $this->hasMany(Plaza::class, 'entidad_id');
    }

    // Historial de verificadores adscritos a la entidad
    public function adscripcions()
    {
        return $this->hasMany(Adscripcion::class, 'entidad_id');
    }

    // Verificador vigente (adscripción activa)
    public function verificadorVigente()
    {
        return $this->hasOne(Adscripcion::class, 'entidad_id')
            ->whereNull('fecha_fin')
            ->latestOfMany('fecha_inicio');
    }

    // Árbol de descendientes (recursivo, vía CTE en el controlador)
    public function descendientes()
    {
        return $this->hasMany(Entidad::class, 'entidad_mayor')
            ->with('descendientes');
    }
}