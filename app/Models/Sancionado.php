<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sancionado extends Model
{
    use SoftDeletes;

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
        'deleted_at'    => 'datetime',
    ];

    // Trabajador al que pertenece el familiar sancionado
    public function trabajador()
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    // Nomenclador de parentesco
    public function parentesco()
    {
        return $this->belongsTo(Parentesco::class, 'parentesco_id');
    }
}