<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plaza extends Model
{
    use SoftDeletes;

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
        'deleted_at' => 'datetime',
    ];

    // Entidad a la que pertenece la plaza
    public function entidad()
    {
        return $this->belongsTo(Entidad::class, 'entidad_id');
    }

    // Historial completo de ocupaciones
    public function ocupacions()
    {
        return $this->hasMany(Ocupacion::class, 'plaza_id');
    }

    // Ocupación actual (fecha_fin NULL)
    public function ocupacionActual()
    {
        return $this->hasOne(Ocupacion::class, 'plaza_id')
            ->whereNull('fecha_fin');
    }

    // Trabajador que ocupa actualmente la plaza (o null)
    public function trabajadorActual()
    {
        return $this->hasOneThrough(
            Trabajador::class,
            Ocupacion::class,
            'plaza_id',        // FK en ocupacions
            'id',              // FK en trabajadors
            'id',              // PK local
            'trabajador_id'    // FK local en ocupacions
        )->whereNull('ocupacions.fecha_fin');
    }

    // Estado derivado (no almacenado)
    public function getEstadoAttribute(): string
    {
        return $this->ocupacionActual()->exists() ? 'ocupada' : 'vacante';
    }

    // Accessor expuesto en JSON
    protected $appends = ['estado'];
}