<?php

namespace App\Models\Negocio\Datos;

use App\Models\Negocio\Nomencladores\GradoAcad;
use Illuminate\Database\Eloquent\Model;

class Academico extends Model
{
    protected $table = 'academicos';

    protected $fillable = [
        'especialista_id',
        'grado_acad_id',
        'titulo',
    ];

    protected $casts = [
        'especialista_id' => 'integer',
        'grado_acad_id'   => 'integer',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function especialista()
    {
        return $this->belongsTo(Especialista::class, 'especialista_id');
    }

    public function gradoAcad()
    {
        return $this->belongsTo(GradoAcad::class, 'grado_acad_id');
    }
}