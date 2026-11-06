<?php

namespace App\Models\Negocio\Nomencladores;

class CategoriaTrop extends Nomenclador
{
    protected $table = 'categoria_trops';

    public function especialistas()
    {
        return $this->hasMany(\App\Models\Negocio\Datos\Especialista::class, 'categoria_trop_id');
    }
}