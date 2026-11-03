<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class MilitanciaTrabajador extends Pivot
{
    use SoftDeletes;

    protected $table = 'militancia_trabajadors';

    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'trabajador_id',
        'militancia_id',
    ];

    protected $casts = [
        'trabajador_id' => 'integer',
        'militancia_id' => 'integer',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'deleted_at'    => 'datetime',
    ];

    public function trabajador()
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    public function militancia()
    {
        return $this->belongsTo(Militancia::class, 'militancia_id');
    }
}