<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trabajador extends Model
{
    use SoftDeletes;

    protected $table = 'trabajadors';

    protected $fillable = [
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
        'grado_mil_id'          => 'integer',
        'escolaridad_id'        => 'integer',
        'categoria_doc_id'      => 'integer',
        'categoria_cien_id'     => 'integer',
        'categoria_trop_id'     => 'integer',
        'categoria_acad_id'     => 'integer',
        'registrador_id'        => 'integer',
        'verificador_id'        => 'integer',
        'aprobador_id'          => 'integer',
        'disposicion_extranjero'=> 'boolean',
        'objetado'              => 'boolean',
        'fecha_captacion'       => 'date',
        'fecha_verificacion'    => 'date',
        'fecha_aprobacion'      => 'date',
        'created_at'            => 'datetime',
        'updated_at'            => 'datetime',
        'deleted_at'            => 'datetime',
    ];

    // ============================
    // Relaciones hacia nomencladores
    // ============================

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

    // ============================
    // Roles del proceso
    // ============================

    public function registrador()
    {
        return $this->belongsTo(Trabajador::class, 'registrador_id');
    }

    public function trabajadorsRegistrados()
    {
        return $this->hasMany(Trabajador::class, 'registrador_id');
    }

    public function verificador()
    {
        return $this->belongsTo(Verificador::class, 'verificador_id');
    }

    public function aprobador()
    {
        return $this->belongsTo(Trabajador::class, 'aprobador_id');
    }

    public function trabajadorsAprobados()
    {
        return $this->hasMany(Trabajador::class, 'aprobador_id');
    }

    // ============================
    // Cuenta y roles
    // ============================

    public function cuenta()
    {
        return $this->hasOne(Cuenta::class, 'trabajador_id');
    }

    public function rols()
    {
        return $this->belongsToMany(Rol::class, 'rol_trabajadors', 'trabajador_id', 'rol_id')
            ->withTimestamps()
            ->withPivot('id', 'deleted_at')
            ->wherePivotNull('deleted_at');
    }

    // ============================
    // Ocupación de plazas
    // ============================

    public function ocupacions()
    {
        return $this->hasMany(Ocupacion::class, 'trabajador_id');
    }

    public function ocupacionActual()
    {
        return $this->hasOne(Ocupacion::class, 'trabajador_id')
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

    // ============================
    // Relaciones M:M (pivotes)
    // ============================

    public function accesos()
    {
        return $this->belongsToMany(Acceso::class, 'acceso_trabajadors', 'trabajador_id', 'acceso_id')
            ->withTimestamps()
            ->withPivot('id', 'deleted_at')
            ->wherePivotNull('deleted_at');
    }

    public function militancias()
    {
        return $this->belongsToMany(Militancia::class, 'militancia_trabajadors', 'trabajador_id', 'militancia_id')
            ->withTimestamps()
            ->withPivot('id', 'deleted_at')
            ->wherePivotNull('deleted_at');
    }

    public function organizacions()
    {
        return $this->belongsToMany(Organizacion::class, 'organizacion_trabajadors', 'trabajador_id', 'organizacion_id')
            ->withTimestamps()
            ->withPivot('id', 'deleted_at')
            ->wherePivotNull('deleted_at');
    }

    public function limitacions()
    {
        return $this->belongsToMany(Limitacion::class, 'limitacion_trabajadors', 'trabajador_id', 'limitacion_id')
            ->withTimestamps()
            ->withPivot('id', 'deleted_at')
            ->wherePivotNull('deleted_at');
    }

    // ============================
    // Hijas del trabajador
    // ============================

    public function delitos()
    {
        return $this->hasMany(Delito::class, 'trabajador_id');
    }

    public function convivientes()
    {
        return $this->hasMany(Conviviente::class, 'trabajador_id');
    }

    public function extranjeros()
    {
        return $this->hasMany(Extranjero::class, 'trabajador_id');
    }

    public function sancionados()
    {
        return $this->hasMany(Sancionado::class, 'trabajador_id');
    }

    public function academicos()
    {
        return $this->hasMany(Academico::class, 'trabajador_id');
    }

    public function cientificos()
    {
        return $this->hasMany(Cientifico::class, 'trabajador_id');
    }
}