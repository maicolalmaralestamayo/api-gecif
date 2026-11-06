<?php

namespace App\Models\Negocio\Nomencladores;

class CategoriaAcad extends Nomenclador
{
    protected $table = 'categoria_acads';

    public function especialistas()
    {
        return $this->hasMany(\App\Models\Negocio\Datos\Especialista::class, 'categoria_acad_id');
    }
}