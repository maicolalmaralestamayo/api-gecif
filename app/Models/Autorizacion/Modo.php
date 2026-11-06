<?php

namespace App\Models\Autorizacion;

use Illuminate\Database\Eloquent\Model;

class Modo extends Model
{
    protected $table    = 'autorizacion.modos';
    protected $fillable = ['nombre', 'descripcion', 'observacion'];

    public function permisosComoAccion()
    {
        return $this->hasMany(Permiso::class, 'modo_accion_id');
    }

    public function permisosComoRecurso()
    {
        return $this->hasMany(Permiso::class, 'modo_recurso_id');
    }

    public function permisosComoCampo()
    {
        return $this->hasMany(PermisoCampo::class, 'modo_campo_id');
    }
}