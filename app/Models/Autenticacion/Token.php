<?php

namespace App\Models\Autenticacion;

use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    protected $table    = 'autorizacion.tokens';
    protected $fillable = ['usuario_id', 'jti', 'exp', 'revocado'];
    protected $casts    = [
        'revocado' => 'boolean',
        'exp'      => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function estaVigente(): bool
    {
        return ! $this->revocado && $this->exp->isFuture();
    }

    public function revocar(): void
    {
        $this->revocado = true;
        $this->save();
    }
}