<?php

namespace App\Models\Negocio\Nomencladores;

class GradoMil extends Nomenclador
{
    protected $table = 'grado_mils';

    public function especialistas()
    {
        return $this->hasMany(\App\Models\Negocio\Datos\Especialista::class, 'grado_mil_id');
    }

    public function verificadors()
    {
        return $this->hasMany(\App\Models\Negocio\Datos\Verificador::class, 'grado_mil_id');
    }
}