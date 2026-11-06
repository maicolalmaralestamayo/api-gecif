<?php

namespace App\Models\Negocio\Datos;

use App\Models\Negocio\Nomencladores\Acceso;
use Illuminate\Database\Eloquent\Model;

class AccesoEspecialista extends Model
{
    protected $table    = 'acceso_especialistas';
    protected $fillable = ['especialista_id', 'acceso_id'];

    protected $casts = [
        'especialista_id' => 'integer',
        'acceso_id'       => 'integer',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function especialista()
    {
        return $this->belongsTo(Especialista::class, 'especialista_id');
    }

    public function acceso()
    {
        return $this->belongsTo(Acceso::class, 'acceso_id');
    }
}