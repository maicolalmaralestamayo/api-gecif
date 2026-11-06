<?php

namespace App\Models\Negocio\Nomencladores;

class Pais extends Nomenclador
{
    protected $table = 'paiss';

    public function extranjeros()
    {
        return $this->hasMany(\App\Models\Negocio\Datos\Extranjero::class, 'pais_id');
    }
}