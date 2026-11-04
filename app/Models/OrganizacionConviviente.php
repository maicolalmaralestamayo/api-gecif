<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class OrganizacionConviviente extends Pivot
{
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