<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedioDetalle extends Model
{
    protected $table = 'orden341.dat_medios_detalles';
    public $timestamps = false;
    protected $primaryKey = 'id';

    public function medio(): BelongsTo
    {
        return $this->belongsTo(
            Medio::class,
            'id_datmedio',
            'id'
        );
    }
}
