<?php

namespace App\Models\Negocio\Nomencladores;

use App\Models\Negocio\Datos\Conviviente;
use App\Models\Negocio\Datos\Especialista;
use App\Models\Negocio\Datos\OrganizacionConviviente;
use App\Models\Negocio\Datos\OrganizacionEspecialista;
use Illuminate\Database\Eloquent\Model;

class Organizacion extends Model
{
    protected $table    = 'organizacions';
    protected $fillable = ['nombre', 'descripcion', 'observacion'];

    public function especialistas()
    {
        return $this->belongsToMany(
            Especialista::class,
            'organizacion_especialistas',
            'organizacion_id',
            'especialista_id'
        )->using(OrganizacionEspecialista::class)
         ->withTimestamps()
         ->withPivot('id');
    }

    public function convivientes()
    {
        return $this->belongsToMany(<?php

namespace App\Models\Negocio\Nomencladores;

use App\Models\Negocio\Datos\Conviviente;
use App\Models\Negocio\Datos\Especialista;
use App\Models\Negocio\Datos\OrganizacionConviviente;
use App\Models\Negocio\Datos\OrganizacionEspecialista;

class Organizacion extends Nomenclador
{
    protected $table = 'organizacions';

    public function especialistas()
    {
        return $this->belongsToMany(
            Especialista::class,
            'organizacion_especialistas',
            'organizacion_id',
            'especialista_id'
        )->using(OrganizacionEspecialista::class)
         ->withTimestamps()
         ->withPivot('id');
    }

    public function convivientes()
    {
        return $this->belongsToMany(
            Conviviente::class,
            'organizacion_convivientes',
            'organizacion_id',
            'conviviente_id'
        )->using(OrganizacionConviviente::class)
         ->withTimestamps()
         ->withPivot('id');
    }
}
            Conviviente::class,
            'organizacion_convivientes',
            'organizacion_id',
            'conviviente_id'
        )->using(OrganizacionConviviente::class)
         ->withTimestamps()
         ->withPivot('id');
    }
}