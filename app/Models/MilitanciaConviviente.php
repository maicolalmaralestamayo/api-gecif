<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class MilitanciaConviviente extends Pivot
{
    protected $table = 'militancia_convivientes';

    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'conviviente_id',
        'militancia_id',
    ];

    protected $casts = [
        'conviviente_id' => 'integer',
        'militancia_id'  => 'integer',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    public function conviviente()
    {
        return $this->belongsTo(Conviviente::class, 'conviviente_id');
    }

    public function militancia()
    {
        return $this->belongsTo(Militancia::class, 'militancia_id');
    }
}