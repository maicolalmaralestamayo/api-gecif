<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cuenta extends Model
{
    use SoftDeletes;

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
        'deleted_at'    => 'datetime',
    ];

    // Trabajador dueño de la cuenta (1:1)
    public function trabajador()
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }
}