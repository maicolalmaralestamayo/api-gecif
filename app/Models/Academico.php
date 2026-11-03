<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Academico extends Model
{
    use SoftDeletes;

    protected $table = 'academicos';

    protected $fillable = [
        'trabajador_id',
        'grado_acad_id',
        'titulo',
    ];

    protected $casts = [
        'trabajador_id' => 'integer',
        'grado_acad_id' => 'integer',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'deleted_at'    => 'datetime',
    ];

    // Trabajador al que pertenece el grado académico
    public function trabajador()
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    // Nomenclador del grado académico
    public function gradoAcad()
    {
        return $this->belongsTo(GradoAcad::class, 'grado_acad_id');
    }
}