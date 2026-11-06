<?php

namespace App\Models\Negocio\Nomencladores;

use App\Models\Negocio\Datos\AccesoEspecialista;
use App\Models\Negocio\Datos\Especialista;

class Acceso extends Nomenclador
{
    protected $table = 'accesos';

    public function especialistas()
    {
        return $this->belongsToMany(
            Especialista::class,
            'acceso_especialistas',
            'acceso_id',
            'especialista_id'
        )->using(AccesoEspecialista::class)
         ->withTimestamps()
         ->withPivot('id');
    }
}