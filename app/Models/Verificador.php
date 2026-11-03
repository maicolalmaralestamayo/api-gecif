<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Verificador extends Model
{
    use SoftDeletes;

    protected $table = 'verificadors';

    protected $fillable = [
        'grado_mil_id',
        'primer_nombre',
        'segundo_nombre',
        'primer_apellido',
        'segundo_apellido',
        'carne',
        'expediente',
    ];

    protected $casts = [
        'grado_mil_id' => 'integer',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
        'deleted_at'   => 'datetime',
    ];

    // Nomenclador de grado militar
    public function gradoMil()
    {
        return $this->belongsTo(GradoMil::class, 'grado_mil_id');
    }

    // Historial de adscripciones a entidades
    public function adscripcions()
    {
        return $this->hasMany(Adscripcion::class, 'verificador_id');
    }

    // Adscripción activa (fecha_fin NULL)
    public function adscripcionActual()
    {
        return $this->hasOne(Adscripcion::class, 'verificador_id')
            ->whereNull('fecha_fin');
    }

    // Entidad vigente del verificador (o null)
    public function entidadActual()
    {
        return $this->adscripcionActual?->entidad;
    }

    // Trabajadores que este verificador ha verificado
    public function trabajadorsVerificados()
    {
        return $this->hasMany(Trabajador::class, 'verificador_id');
    }
}