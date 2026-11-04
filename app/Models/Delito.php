<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delito extends Model
{
    protected $table = 'delitos';

    protected $fillable = [
        'trabajador_id',
        'nombre',
        'sancion',
        'fecha',
    ];

    protected $casts = [
        'trabajador_id' => 'integer',
        'fecha'         => 'date',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public function trabajador()
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }
}