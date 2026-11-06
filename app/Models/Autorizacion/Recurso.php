<?php

namespace App\Models\Autorizacion;

use Illuminate\Database\Eloquent\Model;

class Recurso extends Model
{
    protected $table    = 'autorizacion.recursos';
    protected $fillable = ['nombre', 'display_name', 'schema_fisico', 'tabla_fisica'];

    public function campos()
    {
        return $this->hasMany(Campo::class, 'recurso_id');
    }

    public function permisos()
    {
        return $this->belongsToMany(
            Permiso::class,
            'autorizacion.permiso_recurso',
            'recurso_id',
            'permiso_id'
        );
    }
}