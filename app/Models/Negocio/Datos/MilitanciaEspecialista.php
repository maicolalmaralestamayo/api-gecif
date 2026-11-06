<?php

namespace App\Models\Negocio\Datos;

use App\Models\Negocio\Nomencladores\Militancia;
use Illuminate\Database\Eloquent\Model;

class MilitanciaEspecialista extends Model
{
    protected $table    = 'militancia_especialistas';
    protected $fillable = ['especialista_id', 'militancia_id'];

    protected $casts = [
        'especialista_id' => 'integer',
        'militancia_id'   => 'integer',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function especialista()
    {
        return $this->belongsTo(Especialista::class, 'especialista_id');
    }

    public function militancia()
    {
        return $this->belongsTo(Militancia::class, 'militancia_id');
    }
}