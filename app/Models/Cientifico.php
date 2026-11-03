<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cientifico extends Model
{
    use SoftDeletes;

    protected $table = 'cientificos';

    protected $fillable = [
        'trabajador_id',
        'grado_cien_id',
        'titulo',
    ];

    protected $casts = [
        'trabajador_id' => 'integer',
        'grado_cien_id' => 'integer',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'deleted_at'    => 'datetime',
    ];

    // Trabajador al que pertenece el grado científico
    public function trabajador()
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    // Nomenclador del grado científico
    public function gradoCien()
    {
        return $this->belongsTo(GradoCien::class, 'grado_cien_id');
    }
}