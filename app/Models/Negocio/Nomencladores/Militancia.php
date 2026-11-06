<?php

namespace App\Models\Negocio\Nomencladores;

use App\Models\Negocio\Datos\Conviviente;
use App\Models\Negocio\Datos\Especialista;
use App\Models\Negocio\Datos\MilitanciaConviviente;
use App\Models\Negocio\Datos\MilitanciaEspecialista;

class Militancia extends Nomenclador
{
    protected $table = 'militancias';

    public function especialistas()
    {
        return $this->belongsToMany(
            Especialista::class,
            'militancia_especialistas',
            'militancia_id',
            'especialista_id'
        )->using(MilitanciaEspecialista::class)
         ->withTimestamps()
         ->withPivot('id');
    }

    public function convivientes()
    {
        return $this->belongsToMany(
            Conviviente::class,
            'militancia_convivientes',
            'militancia_id',
            'conviviente_id'
        )->using(MilitanciaConviviente::class)
         ->withTimestamps()
         ->withPivot('id');
    }
}