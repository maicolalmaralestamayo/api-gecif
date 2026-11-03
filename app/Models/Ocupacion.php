<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ocupacion extends Model
{
    use SoftDeletes;

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
        'deleted_at'    => 'datetime',
    ];

    // Trabajador que ocupa (o ocupó) la plaza
    public function trabajador()
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    // Plaza ocupada (o que se ocupó)
    public function plaza()
    {
        return $this->belongsTo(Plaza::class, 'plaza_id');
    }

    // Entidad derivada a través de la plaza
    public function entidad()
    {
        return $this->plaza?->entidad;
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