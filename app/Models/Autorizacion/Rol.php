<?php

namespace App\Models\Autorizacion;

use App\Models\Autenticacion\Usuario;
use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $table    = 'autorizacion.rols';
    protected $fillable = ['nombre', 'display_name'];

    public function usuarios()
    {
        return $this->belongsToMany(
            Usuario::class,
            'autorizacion.rol_usuario',
            'rol_id',
            'usuario_id'
        );
    }

    public function permisos()
    {
        return $this->hasMany(Permiso::class, 'rol_id');
    }
}