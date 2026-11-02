<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medio extends Model
{
    protected $table = 'orden341.dat_medios';
    public $timestamps = false;
    protected $primaryKey = 'id';

    public function detalles(): HasMany
    {
        return $this->hasMany(
            MedioDetalle::class,
            'id_datmedio',
            'id'
        );
    }
}
