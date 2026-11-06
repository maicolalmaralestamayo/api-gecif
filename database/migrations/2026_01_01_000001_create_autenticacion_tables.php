<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS autorizacion');

        // 1. autorizacion.usuarios
        Schema::create('autorizacion.usuarios', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        DB::statement('CREATE INDEX usuarios_activo_idx ON autorizacion.usuarios (activo)');

        // 2. autorizacion.tokens
        Schema::create('autorizacion.tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('usuario_id');
            $table->string('jti', 64)->unique();
            $table->timestamp('exp');
            $table->boolean('revocado')->default(false);
            $table->timestamps();

            $table->foreign('usuario_id')
                  ->references('id')->on('autorizacion.usuarios')
                  ->onDelete('cascade');
            $table->index(['usuario_id', 'revocado']);
            $table->index('exp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autorizacion.tokens');
        Schema::dropIfExists('autorizacion.usuarios');
        DB::statement('DROP SCHEMA IF EXISTS autorizacion');
    }
};