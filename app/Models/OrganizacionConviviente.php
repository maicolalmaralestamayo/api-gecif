<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrganizacionConviviente extends Pivot
{
    use SoftDeletes;

    protected $table = 'organizacion_convivientes';

    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'conviviente_id',
        'organizacion_id',
    ];

    protected $casts = [
        'conviviente_id'  => 'integer',
        'organizacion_id' => 'integer',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
        'deleted_at'      => 'datetime',
    ];

    public function conviviente()
    {
        return $this->belongsTo(Conviviente::class, 'conviviente_id');
    }

    public function organizacion()
    {
        return $this->belongsTo(Organizacion::class, 'organizacion_id');
    }
}