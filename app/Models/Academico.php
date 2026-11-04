<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Academico extends Model
{
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
    ];

    public function trabajador()
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    public function gradoAcad()
    {
        return $this->belongsTo(GradoAcad::class, 'grado_acad_id');
    }
}