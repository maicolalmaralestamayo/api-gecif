<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class RolTrabajador extends Pivot
{
    protected $table = 'rol_trabajadors';

    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'trabajador_id',
        'rol_id',
    ];

    protected $casts = [
        'trabajador_id' => 'integer',
        'rol_id'        => 'integer',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public function trabajador()
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }
}