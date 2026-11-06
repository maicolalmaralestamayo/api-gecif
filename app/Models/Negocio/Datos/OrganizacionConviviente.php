<?php

namespace App\Models\Negocio\Datos;

use App\Models\Negocio\Nomencladores\Organizacion;
use Illuminate\Database\Eloquent\Model;

class OrganizacionConviviente extends Model
{
    protected $table    = 'organizacion_convivientes';
    protected $fillable = ['conviviente_id', 'organizacion_id'];

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