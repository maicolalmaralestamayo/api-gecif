<?php

namespace App\Models\Negocio\Datos;

use App\Models\Negocio\Nomencladores\Militancia;
use Illuminate\Database\Eloquent\Model;

class MilitanciaConviviente extends Model
{
    protected $table    = 'militancia_convivientes';
    protected $fillable = ['conviviente_id', 'militancia_id'];

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