<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cuenta extends Model
{
    protected $table = 'cuentas';

    protected $fillable = [
        'trabajador_id',
        'usuario',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'trabajador_id' => 'integer',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public function trabajador()
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }
}