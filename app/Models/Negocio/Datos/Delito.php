<?php

namespace App\Models\Negocio\Datos;

use Illuminate\Database\Eloquent\Model;

class Delito extends Model
{
    protected $table = 'delitos';

    protected $fillable = [
        'especialista_id',
        'nombre',
        'sancion',
        'fecha',
    ];

    protected $casts = [
        'especialista_id' => 'integer',
        'fecha'           => 'date',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function especialista()
    {
        return $this->belongsTo(Especialista::class, 'especialista_id');
    }
}