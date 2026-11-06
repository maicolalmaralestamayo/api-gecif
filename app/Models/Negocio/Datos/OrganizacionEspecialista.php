<?php

namespace App\Models\Negocio\Datos;

use App\Models\Negocio\Nomencladores\Organizacion;
use Illuminate\Database\Eloquent\Model;

class OrganizacionEspecialista extends Model
{
    protected $table    = 'organizacion_especialistas';
    protected $fillable = ['especialista_id', 'organizacion_id'];

    protected $casts = [
        'especialista_id' => 'integer',
        'organizacion_id' => 'integer',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function especialista()
    {
        return $this->belongsTo(Especialista::class, 'especialista_id');
    }

    public function organizacion()
    {
        return $this->belongsTo(Organizacion::class, 'organizacion_id');
    }
}