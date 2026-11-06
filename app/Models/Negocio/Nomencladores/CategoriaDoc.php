<?php

namespace App\Models\Negocio\Nomencladores;

class CategoriaDoc extends Nomenclador
{
    protected $table = 'categoria_docs';

    public function especialistas()
    {
        return $this->hasMany(\App\Models\Negocio\Datos\Especialista::class, 'categoria_doc_id');
    }
}