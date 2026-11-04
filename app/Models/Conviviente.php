<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conviviente extends Model
{
    protected $table = 'convivientes';

    protected $fillable = [
        'trabajador_id',
        'nombres_apellidos',
        'parentesco_id',
        'centro_trabajo',
        'plaza',
    ];

    protected $casts = [
        'trabajador_id' => 'integer',
        'parentesco_id' => 'integer',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public function trabajador()
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    public function parentesco()
    {
        return $this->belongsTo(Parentesco::class, 'parentesco_id');
    }

    public function militancias()
    {
        return $this->belongsToMany(Militancia::class, 'militancia_convivientes', 'conviviente_id', 'militancia_id')
            ->withTimestamps()
            ->withPivot('id');
    }

    public function organizacions()
    {
        return $this->belongsToMany(Organizacion::class, 'organizacion_convivientes', 'conviviente_id', 'organizacion_id')
            ->withTimestamps()
            ->withPivot('id');
    }
}