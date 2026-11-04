<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sancionado extends Model
{
    protected $table = 'sancionados';

    protected $fillable = [
        'trabajador_id',
        'nombres_apellidos',
        'parentesco_id',
        'delito',
        'sancion',
    ];

    protected $casts = [
        'trabajador_id' => 'integer',
        'parentesco_id' => 'integer',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public function trabajador()
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    public function parentesco()
    {
        return $this->belongsTo(Parentesco::class, 'parentesco_id');
    }
}