<?php

namespace App\Models\Negocio\Datos;

use App\Models\Negocio\Nomencladores\GradoCien;
use Illuminate\Database\Eloquent\Model;

class Cientifico extends Model
{
    protected $table = 'cientificos';

    protected $fillable = [
        'especialista_id',
        'grado_cien_id',
        'titulo',
    ];

    protected $casts = [
        'especialista_id' => 'integer',
        'grado_cien_id'   => 'integer',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function especialista()
    {
        return $this->belongsTo(Especialista::class, 'especialista_id');
    }

    public function gradoCien()
    {
        return $this->belongsTo(GradoCien::class, 'grado_cien_id');
    }
}