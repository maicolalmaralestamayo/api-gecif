<?php

namespace App\Models\Negocio\Datos;

use App\Models\Negocio\Nomencladores\Militancia;
use App\Models\Negocio\Nomencladores\Organizacion;
use App\Models\Negocio\Nomencladores\Parentesco;
use Illuminate\Database\Eloquent\Model;

class Conviviente extends Model
{
    protected $table = 'convivientes';

    protected $fillable = [
        'especialista_id',
        'nombres_apellidos',
        'parentesco_id',
        'centro_trabajo',
        'plaza',
    ];

    protected $casts = [
        'especialista_id' => 'integer',
        'parentesco_id'   => 'integer',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function especialista()
    {
        return $this->belongsTo(Especialista::class, 'especialista_id');
    }

    public function parentesco()
    {
        return $this->belongsTo(Parentesco::class, 'parentesco_id');
    }

    public function militancias()
    {
        return $this->belongsToMany(
            Militancia::class,
            'militancia_convivientes',
            'conviviente_id',
            'militancia_id'
        )->using(MilitanciaConviviente::class)
         ->withTimestamps()
         ->withPivot('id');
    }

    public function organizacions()
    {
        return $this->belongsToMany(
            Organizacion::class,
            'organizacion_convivientes',
            'conviviente_id',
            'organizacion_id'
        )->using(OrganizacionConviviente::class)
         ->withTimestamps()
         ->withPivot('id');
    }
}