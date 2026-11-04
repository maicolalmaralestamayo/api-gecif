<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plaza extends Model
{
    protected $table = 'plazas';

    protected $fillable = [
        'nombre',
        'entidad_id',
        'observacion',
    ];

    protected $casts = [
        'entidad_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['estado'];

    public function entidad()
    {
        return $this->belongsTo(Entidad::class, 'entidad_id');
    }

    public function ocupacions()
    {
        return $this->hasMany(Ocupacion::class, 'plaza_id');
    }

    public function ocupacionActual()
    {
        return $this->hasOne(Ocupacion::class, 'plaza_id')
            ->whereNull('fecha_fin');
    }

    public function trabajadorActual()
    {
        return $this->ocupacionActual?->trabajador;
    }

    public function getEstadoAttribute(): string
    {
        return $this->ocupacionActual ? 'ocupada' : 'vacante';
    }
}