<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cientifico extends Model
{
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
    ];

    public function trabajador()
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    public function gradoCien()
    {
        return $this->belongsTo(GradoCien::class, 'grado_cien_id');
    }
}