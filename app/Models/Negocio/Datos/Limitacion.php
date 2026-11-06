<?php

namespace App\Models\Negocio\Datos;

use Illuminate\Database\Eloquent\Model;

class Limitacion extends Model
{
    protected $table    = 'limitacions';
    protected $fillable = ['especialista_id', 'nombre', 'descripcion', 'observacion'];

    protected $casts = [
        'especialista_id' => 'integer',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function especialista()
    {
        return $this->belongsTo(Especialista::class, 'especialista_id');
    }
}