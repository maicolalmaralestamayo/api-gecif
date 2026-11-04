<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ocupacion extends Model
{
    protected $table = 'ocupacions';

    protected $fillable = [
        'trabajador_id',
        'plaza_id',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $casts = [
        'trabajador_id' => 'integer',
        'plaza_id'      => 'integer',
        'fecha_inicio'  => 'date',
        'fecha_fin'     => 'date',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public function trabajador()
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    public function plaza()
    {
        return $this->belongsTo(Plaza::class, 'plaza_id');
    }

    public function entidad()
    {
        return $this->plaza?->entidad;
    }

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