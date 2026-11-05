<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tablas = [
        'grado_mils',
        'escolaridads',
        'categoria_docs',
        'categoria_ciens',
        'categoria_trops',
        'categoria_acads',
        'grado_acads',
        'grado_ciens',
        'accesos',
        'militancias',
        'organizacions',
        'limitacions',
        'parentescos',
        'paiss',
    ];

    public function up(): void
    {
        foreach ($this->tablas as $tabla) {
            Schema::create($tabla, function (Blueprint $table) {
                $table->id();
                $table->string('nombre')->unique();
                $table->string('descripcion')->nullable();
                $table->string('observacion')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tablas) as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};