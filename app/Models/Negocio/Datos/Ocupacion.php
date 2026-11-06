<?php

namespace App\Models\Negocio\Datos;

use Illuminate\Database\Eloquent\Model;

class Ocupacion extends Model
{
    protected $table = 'ocupacions';

    protected $fillable = [
        'especialista_id',
        'plaza_id',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $casts = [
        'especialista_id' => 'integer',
        'plaza_id'        => 'integer',
        'fecha_inicio'    => 'date',
        'fecha_fin'       => 'date',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function especialista()
    {
        return $this->belongsTo(Especialista::class, 'especialista_id');
    }

    public function plaza()
    {
        return $this->belongsTo(Plaza::class, 'plaza_id');
    }
}