<?php

namespace App\Models;

class Rol extends Nomenclador
{
    protected $table = 'rols';

    public function trabajadors()
    {
        return $this->belongsToMany(Trabajador::class, 'rol_trabajadors', 'rol_id', 'trabajador_id')
            ->withTimestamps()
            ->withPivot('id');
    }
}