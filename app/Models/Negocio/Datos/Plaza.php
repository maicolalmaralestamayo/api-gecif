<?php

namespace App\Models\Negocio\Datos;

use Illuminate\Database\Eloquent\Model;

class Plaza extends Model
{
    protected $table = 'plazas';

    protected $fillable = [
        'nombre',
        'entidad_id',
        'observacion',
    ];

    protected $casts = [
        'entidad_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function entidad()
    {
        return $this->belongsTo(Entidad::class, 'entidad_id');
    }

    public function ocupacions()
    {
        return $this->hasMany(Ocupacion::class, 'plaza_id');
    }

    public function ocupacionActual()
    {
        return $this->hasOne(Ocupacion::class, 'plaza_id')
            ->whereNull('fecha_fin');
    }
}