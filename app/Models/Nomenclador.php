<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class Nomenclador extends Model
{
    protected $fillable = [
        'nombre',
        'descripcion',
        'observacion',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}