<?php

namespace App\Models\Negocio\Datos;

use App\Models\Negocio\Nomencladores\Pais;
use App\Models\Negocio\Nomencladores\Parentesco;
use Illuminate\Database\Eloquent\Model;

class Extranjero extends Model
{
    protected $table = 'extranjeros';

    protected $fillable = [
        'especialista_id',
        'nombres_apellidos',
        'parentesco_id',
        'pais_id',
        'mantiene_relacion',
    ];

    protected $casts = [
        'especialista_id'   => 'integer',
        'parentesco_id'     => 'integer',
        'pais_id'           => 'integer',
        'mantiene_relacion' => 'boolean',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    public function especialista()
    {
        return $this->belongsTo(Especialista::class, 'especialista_id');
    }

    public function parentesco()
    {
        return $this->belongsTo(Parentesco::class, 'parentesco_id');
    }

    public function pais()
    {
        return $this->belongsTo(Pais::class, 'pais_id');
    }
}