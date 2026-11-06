<?php

namespace App\Models\Negocio\Datos;

use App\Models\Negocio\Nomencladores\Parentesco;
use Illuminate\Database\Eloquent\Model;

class Sancionado extends Model
{
    protected $table = 'sancionados';

    protected $fillable = [
        'especialista_id',
        'nombres_apellidos',
        'parentesco_id',
        'delito',
        'sancion',
    ];

    protected $casts = [
        'especialista_id' => 'integer',
        'parentesco_id'   => 'integer',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function especialista()
    {
        return $this->belongsTo(Especialista::class, 'especialista_id');
    }

    public function parentesco()
    {
        return $this->belongsTo(Parentesco::class, 'parentesco_id');
    }
}