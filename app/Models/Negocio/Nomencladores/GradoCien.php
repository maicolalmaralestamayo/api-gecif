<?php

namespace App\Models\Negocio\Nomencladores;

class GradoCien extends Nomenclador
{
    protected $table = 'grado_ciens';

    public function cientificos()
    {
        return $this->hasMany(\App\Models\Negocio\Datos\Cientifico::class, 'grado_cien_id');
    }
}