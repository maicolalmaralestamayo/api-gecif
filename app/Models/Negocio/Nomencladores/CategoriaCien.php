<?php

namespace App\Models\Negocio\Nomencladores;

class CategoriaCien extends Nomenclador
{
    protected $table = 'categoria_ciens';

    public function especialistas()
    {
        return $this->hasMany(\App\Models\Negocio\Datos\Especialista::class, 'categoria_cien_id');
    }
}