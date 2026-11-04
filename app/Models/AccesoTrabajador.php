<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class AccesoTrabajador extends Pivot
{
    protected $table = 'acceso_trabajadors';

    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'trabajador_id',
        'acceso_id',
    ];

    protected $casts = [
        'trabajador_id' => 'integer',
        'acceso_id'     => 'integer',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public function trabajador()
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    public function acceso()
    {
        return $this->belongsTo(Acceso::class, 'acceso_id');
    }
}