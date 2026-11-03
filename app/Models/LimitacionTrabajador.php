<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class LimitacionTrabajador extends Pivot
{
    use SoftDeletes;

    protected $table = 'limitacion_trabajadors';

    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'trabajador_id',
        'limitacion_id',
    ];

    protected $casts = [
        'trabajador_id' => 'integer',
        'limitacion_id' => 'integer',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'deleted_at'    => 'datetime',
    ];

    public function trabajador()
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    public function limitacion()
    {
        return $this->belongsTo(Limitacion::class, 'limitacion_id');
    }
}