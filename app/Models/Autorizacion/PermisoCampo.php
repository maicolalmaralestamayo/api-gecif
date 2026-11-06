<?php

namespace App\Models\Autorizacion;

use Illuminate\Database\Eloquent\Model;

class PermisoCampo extends Model
{
    protected $table    = 'autorizacion.permisos_campo';
    protected $fillable = ['permiso_id', 'modo_campo_id'];

    public function permiso()
    {
        return $this->belongsTo(Permiso::class, 'permiso_id');
    }

    public function modoCampo()
    {
        return $this->belongsTo(Modo::class, 'modo_campo_id');
    }

    public function campos()
    {
        return $this->belongsToMany(
            Campo::class,
            'autorizacion.campo_permiso',
            'permisos_campo_id',
            'campo_id'
        );
    }
}