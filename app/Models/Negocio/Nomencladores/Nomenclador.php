<?php

namespace App\Models\Negocio\Nomencladores;

use Illuminate\Database\Eloquent\Model;

abstract class Nomenclador extends Model
{
    protected $fillable = ['nombre', 'descripcion', 'observacion'];
}