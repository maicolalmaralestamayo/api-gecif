<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Delito extends Model
{
    use SoftDeletes;

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
        'deleted_at'    => 'datetime',
    ];

    // Trabajador al que pertenece el delito
    public function trabajador()
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }
}