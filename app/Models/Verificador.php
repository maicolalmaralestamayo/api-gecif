<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Verificador extends Model
{
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
    ];

    public function gradoMil()
    {
        return $this->belongsTo(GradoMil::class, 'grado_mil_id');
    }

    public function adscripcions()
    {
        return $this->hasMany(Adscripcion::class, 'verificador_id');
    }

    public function adscripcionActual()
    {
        return $this->hasOne(Adscripcion::class, 'verificador_id')
            ->whereNull('fecha_fin');
    }

    public function entidadActual()
    {
        return $this->adscripcionActual?->entidad;
    }

    public function trabajadorsVerificados()
    {
        return $this->hasMany(Trabajador::class, 'verificador_id');
    }
}