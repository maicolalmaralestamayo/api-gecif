<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Extranjero extends Model
{
    protected $table = 'extranjeros';

    protected $fillable = [
        'trabajador_id',
        'nombres_apellidos',
        'parentesco_id',
        'pais_id',
        'mantiene_relacion',
    ];

    protected $casts = [
        'trabajador_id'     => 'integer',
        'parentesco_id'     => 'integer',
        'pais_id'           => 'integer',
        'mantiene_relacion' => 'boolean',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    public function trabajador()
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    public function parentesco()
    {
        return $this->belongsTo(Parentesco::class, 'parentesco_id');
    }

    public function pais()
    {
        return $this->belongsTo(Pais::class, 'pais_id');
    }
}