<?php

namespace App\Models\Negocio\Nomencladores;

class Escolaridad extends Nomenclador
{
    protected $table = 'escolaridads';

    public function especialistas()
    {
        return $this->hasMany(\App\Models\Negocio\Datos\Especialista::class, 'escolaridad_id');
    }
}