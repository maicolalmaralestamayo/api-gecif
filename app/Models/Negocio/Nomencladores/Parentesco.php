<?php

namespace App\Models\Negocio\Nomencladores;

class Parentesco extends Nomenclador
{
    protected $table = 'parentescos';

    public function convivientes()
    {
        return $this->hasMany(\App\Models\Negocio\Datos\Conviviente::class, 'parentesco_id');
    }

    public function extranjeros()
    {
        return $this->hasMany(\App\Models\Negocio\Datos\Extranjero::class, 'parentesco_id');
    }

    public function sancionados()
    {
        return $this->hasMany(\App\Models\Negocio\Datos\Sancionado::class, 'parentesco_id');
    }
}