<?php

namespace App\Models\Autorizacion;

use Illuminate\Database\Eloquent\Model;

class Campo extends Model
{
    protected $table    = 'autorizacion.campos';
    protected $fillable = ['recurso_id', 'nombre', 'display_name'];

    public function recurso()
    {
        return $this->belongsTo(Recurso::class, 'recurso_id');
    }

    public function permisosCampo()
    {
        return $this->belongsToMany(
            PermisoCampo::class,
            'autorizacion.campo_permiso',
            'campo_id',
            'permisos_campo_id'
        );
    }
}