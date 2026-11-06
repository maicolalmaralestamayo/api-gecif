<?php

namespace App\Models\Autorizacion;

use Illuminate\Database\Eloquent\Model;

class Accion extends Model
{
    protected $table    = 'autorizacion.acciones';
    protected $fillable = ['nombre', 'descripcion', 'observacion', 'permite_campos'];
    protected $casts    = ['permite_campos' => 'boolean'];

    public function permisos()
    {
        return $this->belongsToMany(
            Permiso::class,
            'autorizacion.permiso_accion',
            'accion_id',
            'permiso_id'
        );
    }
}