<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conviviente extends Model
{
    use SoftDeletes;

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
        'deleted_at'    => 'datetime',
    ];

    // Trabajador al que pertenece el familiar conviviente
    public function trabajador()
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    // Nomenclador de parentesco
    public function parentesco()
    {
        return $this->belongsTo(Parentesco::class, 'parentesco_id');
    }

    // Militancias del familiar conviviente (M:M)
    public function militancias()
    {
        return $this->belongsToMany(Militancia::class, 'militancia_convivientes', 'conviviente_id', 'militancia_id')
            ->withTimestamps()
            ->withPivot('id', 'deleted_at')
            ->wherePivotNull('deleted_at');
    }

    // Organizaciones del familiar conviviente (M:M)
    public function organizacions()
    {
        return $this->belongsToMany(Organizacion::class, 'organizacion_convivientes', 'conviviente_id', 'organizacion_id')
            ->withTimestamps()
            ->withPivot('id', 'deleted_at')
            ->wherePivotNull('deleted_at');
    }
}