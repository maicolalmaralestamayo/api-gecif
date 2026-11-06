<?php

namespace App\Models\Autenticacion;

use App\Models\Autorizacion\Rol;
use App\Models\Especialista;
use Illuminate\Database\Eloquent\Model;

class Usuario extends Model
{
    protected $table    = 'autorizacion.usuarios';
    protected $fillable = ['nombre', 'activo'];
    protected $casts    = ['activo' => 'boolean'];

    public function tokens()
    {
        return $this->hasMany(Token::class, 'usuario_id');
    }

    public function rols()
    {
        return $this->belongsToMany(
            Rol::class,
            'autorizacion.rol_usuario',
            'usuario_id',
            'rol_id'
        );
    }

    public function especialista()
    {
        return $this->hasOne(Especialista::class, 'usuario_id');
    }
}