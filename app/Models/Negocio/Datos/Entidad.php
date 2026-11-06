<?php

namespace App\Models\Negocio\Datos;

use Illuminate\Database\Eloquent\Model;

class Entidad extends Model
{
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
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public function entidadMayor()
    {
        return $this->belongsTo(Entidad::class, 'entidad_mayor');
    }

    public function entidadesMenores()
    {
        return $this->hasMany(Entidad::class, 'entidad_mayor');
    }

    public function plazas()
    {
        return $this->hasMany(Plaza::class, 'entidad_id');
    }

    public function adscripcions()
    {
        return $this->hasMany(Adscripcion::class, 'entidad_id');
    }
}