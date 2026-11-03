<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Adscripcion extends Model
{
    use SoftDeletes;

    protected $table = 'adscripcions';

    protected $fillable = [
        'verificador_id',
        'entidad_id',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $casts = [
        'verificador_id' => 'integer',
        'entidad_id'     => 'integer',
        'fecha_inicio'   => 'date',
        'fecha_fin'      => 'date',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
        'deleted_at'     => 'datetime',
    ];

    // Verificador adscrito
    public function verificador()
    {
        return $this->belongsTo(Verificador::class, 'verificador_id');
    }

    // Entidad donde está (o estuvo) adscrito
    public function entidad()
    {
        return $this->belongsTo(Entidad::class, 'entidad_id');
    }

    // Scopes de conveniencia
    public function scopeActiva($query)
    {
        return $query->whereNull('fecha_fin');
    }

    public function scopeCerrada($query)
    {
        return $query->whereNotNull('fecha_fin');
    }

    public function esActiva(): bool
    {
        return is_null($this->fecha_fin);
    }
}