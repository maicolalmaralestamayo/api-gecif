<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class OrganizacionTrabajador extends Pivot
{
    protected $table = 'organizacion_trabajadors';

    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'trabajador_id',
        'organizacion_id',
    ];

    protected $casts = [
        'trabajador_id'   => 'integer',
        'organizacion_id' => 'integer',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function trabajador()
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    public function organizacion()
    {
        return $this->belongsTo(Organizacion::class, 'organizacion_id');
    }
}