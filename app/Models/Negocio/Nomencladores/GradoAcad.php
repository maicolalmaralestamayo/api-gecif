<?php

namespace App\Models\Negocio\Nomencladores;

class GradoAcad extends Nomenclador
{
    protected $table = 'grado_acads';

    public function academicos()
    {
        return $this->hasMany(\App\Models\Negocio\Datos\Academico::class, 'grado_acad_id');
    }
}