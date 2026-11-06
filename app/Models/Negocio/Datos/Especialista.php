<?php

namespace App\Models\Negocio\Datos;

use App\Models\Autenticacion\Usuario;
use App\Models\Negocio\Nomencladores\Acceso;
use App\Models\Negocio\Nomencladores\CategoriaAcad;
use App\Models\Negocio\Nomencladores\CategoriaCien;
use App\Models\Negocio\Nomencladores\CategoriaDoc;
use App\Models\Negocio\Nomencladores\CategoriaTrop;
use App\Models\Negocio\Nomencladores\Escolaridad;
use App\Models\Negocio\Nomencladores\GradoMil;
use App\Models\Negocio\Nomencladores\Militancia;
use App\Models\Negocio\Nomencladores\Organizacion;
use Illuminate\Database\Eloquent\Model;

class Especialista extends Model
{
    protected $table = 'especialistas';

    protected $fillable = [
        'usuario_id',
        'grado_mil_id',
        'primer_nombre',
        'segundo_nombre',
        'primer_apellido',
        'segundo_apellido',
        'natural_de',
        'carne',
        'expediente',
        'direccion_particular',
        'direccion_residencia',
        'motivos',
        'disposicion_extranjero',
        'criterio',
        'juramento',
        'objetado',
        'activo',
        'escolaridad_id',
        'categoria_doc_id',
        'categoria_cien_id',
        'categoria_trop_id',
        'categoria_acad_id',
        'registrador_id',
        'fecha_captacion',
        'verificador_id',
        'fecha_verificacion',
        'aprobador_id',
        'fecha_aprobacion',
    ];

    protected $casts = [
        'usuario_id'             => 'integer',
        'grado_mil_id'           => 'integer',
        'escolaridad_id'         => 'integer',
        'categoria_doc_id'       => 'integer',
        'categoria_cien_id'      => 'integer',
        'categoria_trop_id'      => 'integer',
        'categoria_acad_id'      => 'integer',
        'registrador_id'         => 'integer',
        'verificador_id'         => 'integer',
        'aprobador_id'           => 'integer',
        'disposicion_extranjero' => 'boolean',
        'objetado'               => 'boolean',
        'activo'                 => 'boolean',
        'fecha_captacion'        => 'date',
        'fecha_verificacion'     => 'date',
        'fecha_aprobacion'       => 'date',
        'created_at'             => 'datetime',
        'updated_at'             => 'datetime',
    ];

    // Usuario (login)
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    // Nomencladores
    public function gradoMil()
    {
        return $this->belongsTo(GradoMil::class, 'grado_mil_id');
    }

    public function escolaridad()
    {
        return $this->belongsTo(Escolaridad::class, 'escolaridad_id');
    }

    public function categoriaDoc()
    {
        return $this->belongsTo(CategoriaDoc::class, 'categoria_doc_id');
    }

    public function categoriaCien()
    {
        return $this->belongsTo(CategoriaCien::class, 'categoria_cien_id');
    }

    public function categoriaTrop()
    {
        return $this->belongsTo(CategoriaTrop::class, 'categoria_trop_id');
    }

    public function categoriaAcad()
    {
        return $this->belongsTo(CategoriaAcad::class, 'categoria_acad_id');
    }

    // Roles del proceso
    public function registrador()
    {
        return $this->belongsTo(Especialista::class, 'registrador_id');
    }

    public function especialistasRegistrados()
    {
        return $this->hasMany(Especialista::class, 'registrador_id');
    }

    public function verificador()
    {
        return $this->belongsTo(Verificador::class, 'verificador_id');
    }

    public function aprobador()
    {
        return $this->belongsTo(Especialista::class, 'aprobador_id');
    }

    public function especialistasAprobados()
    {
        return $this->hasMany(Especialista::class, 'aprobador_id');
    }

    // Ocupación de plazas
    public function ocupacions()
    {
        return $this->hasMany(Ocupacion::class, 'especialista_id');
    }

    public function ocupacionActual()
    {
        return $this->hasOne(Ocupacion::class, 'especialista_id')
            ->whereNull('fecha_fin');
    }

    public function plazaActual()
    {
        return $this->ocupacionActual?->plaza;
    }

    public function entidadActual()
    {
        return $this->plazaActual?->entidad;
    }

    // Pivotes M:M (con ->using)
    public function accesos()
    {
        return $this->belongsToMany(
            Acceso::class,
            'acceso_especialistas',
            'especialista_id',
            'acceso_id'
        )->using(AccesoEspecialista::class)
         ->withTimestamps()
         ->withPivot('id');
    }

    public function militancias()
    {
        return $this->belongsToMany(
            Militancia::class,
            'militancia_especialistas',
            'especialista_id',
            'militancia_id'
        )->using(MilitanciaEspecialista::class)
         ->withTimestamps()
         ->withPivot('id');
    }

    public function organizacions()
    {
        return $this->belongsToMany(
            Organizacion::class,
            'organizacion_especialistas',
            'especialista_id',
            'organizacion_id'
        )->using(OrganizacionEspecialista::class)
         ->withTimestamps()
         ->withPivot('id');
    }

    // Limitaciones (1:N, sin pivote)
    public function limitacions()
    {
        return $this->hasMany(Limitacion::class, 'especialista_id');
    }

    // Hijas
    public function delitos()
    {
        return $this->hasMany(Delito::class, 'especialista_id');
    }

    public function convivientes()
    {
        return $this->hasMany(Conviviente::class, 'especialista_id');
    }

    public function extranjeros()
    {
        return $this->hasMany(Extranjero::class, 'especialista_id');
    }

    public function sancionados()
    {
        return $this->hasMany(Sancionado::class, 'especialista_id');
    }

    public function academicos()
    {
        return $this->hasMany(Academico::class, 'especialista_id');
    }

    public function cientificos()
    {
        return $this->hasMany(Cientifico::class, 'especialista_id');
    }
}