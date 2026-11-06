<?php

namespace App\Models\Autorizacion;

use Illuminate\Database\Eloquent\Model;

class Permiso extends Model
{
    protected $table    = 'autorizacion.permisos';
    protected $fillable = ['rol_id', 'modo_accion_id', 'modo_recurso_id'];

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function modoAccion()
    {
        return $this->belongsTo(Modo::class, 'modo_accion_id');
    }

    public function modoRecurso()
    {
        return $this->belongsTo(Modo::class, 'modo_recurso_id');
    }

    public function acciones()
    {
        return $this->belongsToMany(
            Accion::class,
            'autorizacion.permiso_accion',
            'permiso_id',
            'accion_id'
        );
    }

    public function recursos()
    {
        return $this->belongsToMany(
            Recurso::class,
            'autorizacion.permiso_recurso',
            'permiso_id',
            'recurso_id'
        );
    }

    public function permisosCampo()
    {
        return $this->hasOne(PermisoCampo::class, 'permiso_id');
    }
}