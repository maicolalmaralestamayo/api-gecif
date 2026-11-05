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

        // 2. autorizacion.rols
        Schema::create('autorizacion.rols', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 100)->unique();
            $table->string('display_name', 150);
            $table->timestamps();
        });

        // 3. autorizacion.recursos
        Schema::create('autorizacion.recursos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 100)->unique();
            $table->string('display_name', 150);
            $table->string('schema_fisico', 100)->nullable();
            $table->string('tabla_fisica', 100)->nullable();
            $table->timestamps();
        });

        // 4. autorizacion.campos
        Schema::create('autorizacion.campos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('recurso_id');
            $table->string('nombre', 100);
            $table->string('display_name', 150);
            $table->timestamps();

            $table->foreign('recurso_id')
                ->references('id')->on('autorizacion.recursos')
                ->onDelete('cascade');
            $table->unique(['recurso_id', 'nombre']);
        });

        // 5. autorizacion.acciones
        Schema::create('autorizacion.acciones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 50)->unique();
            $table->string('descripcion')->nullable();
            $table->text('observacion')->nullable();
            $table->boolean('permite_campos')->default(true);
            $table->timestamps();
        });

        // 6. autorizacion.modos
        Schema::create('autorizacion.modos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 50)->unique();
            $table->string('descripcion')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
        });

        // 7. autorizacion.rol_usuario
        Schema::create('autorizacion.rol_usuario', function (Blueprint $table) {
            $table->unsignedBigInteger('rol_id');
            $table->unsignedBigInteger('usuario_id');
            $table->primary(['rol_id', 'usuario_id']);
            $table->timestamps();

            $table->foreign('rol_id')->references('id')->on('autorizacion.rols')->onDelete('cascade');
            $table->foreign('usuario_id')->references('id')->on('autorizacion.usuarios')->onDelete('cascade');
        });

        // 8. autorizacion.permisos
        Schema::create('autorizacion.permisos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('rol_id');
            $table->unsignedBigInteger('modo_accion_id');
            $table->unsignedBigInteger('modo_recurso_id');
            $table->timestamps();

            $table->foreign('rol_id')->references('id')->on('autorizacion.rols')->onDelete('cascade');
            $table->foreign('modo_accion_id')->references('id')->on('autorizacion.modos')->onDelete('cascade');
            $table->foreign('modo_recurso_id')->references('id')->on('autorizacion.modos')->onDelete('cascade');
            $table->index('rol_id');
        });

        // 9. autorizacion.permiso_accion
        Schema::create('autorizacion.permiso_accion', function (Blueprint $table) {
            $table->unsignedBigInteger('permiso_id');
            $table->unsignedBigInteger('accion_id');
            $table->primary(['permiso_id', 'accion_id']);
            $table->timestamps();

            $table->foreign('permiso_id')->references('id')->on('autorizacion.permisos')->onDelete('cascade');
            $table->foreign('accion_id')->references('id')->on('autorizacion.acciones')->onDelete('cascade');
        });

        // 10. autorizacion.permiso_recurso
        Schema::create('autorizacion.permiso_recurso', function (Blueprint $table) {
            $table->unsignedBigInteger('permiso_id');
            $table->unsignedBigInteger('recurso_id');
            $table->primary(['permiso_id', 'recurso_id']);
            $table->timestamps();

            $table->foreign('permiso_id')->references('id')->on('autorizacion.permisos')->onDelete('cascade');
            $table->foreign('recurso_id')->references('id')->on('autorizacion.recursos')->onDelete('cascade');
        });

        // 11. autorizacion.permisos_campo
        Schema::create('autorizacion.permisos_campo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('permiso_id');
            $table->unsignedBigInteger('modo_campo_id');
            $table->timestamps();

            $table->foreign('permiso_id')->references('id')->on('autorizacion.permisos')->onDelete('cascade');
            $table->foreign('modo_campo_id')->references('id')->on('autorizacion.modos')->onDelete('cascade');
            $table->unique('permiso_id');
        });

        // 12. autorizacion.campo_permiso
        Schema::create('autorizacion.campo_permiso', function (Blueprint $table) {
            $table->unsignedBigInteger('permisos_campo_id');
            $table->unsignedBigInteger('campo_id');
            $table->primary(['permisos_campo_id', 'campo_id']);
            $table->timestamps();

            $table->foreign('permisos_campo_id')
                ->references('id')->on('autorizacion.permisos_campo')
                ->onDelete('cascade');
            $table->foreign('campo_id')
                ->references('id')->on('autorizacion.campos')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autorizacion.campo_permiso');
        Schema::dropIfExists('autorizacion.permisos_campo');
        Schema::dropIfExists('autorizacion.permiso_recurso');
        Schema::dropIfExists('autorizacion.permiso_accion');
        Schema::dropIfExists('autorizacion.permisos');
        Schema::dropIfExists('autorizacion.rol_usuario');
        Schema::dropIfExists('autorizacion.modos');
        Schema::dropIfExists('autorizacion.acciones');
        Schema::dropIfExists('autorizacion.campos');
        Schema::dropIfExists('autorizacion.recursos');
        Schema::dropIfExists('autorizacion.rols');
        Schema::dropIfExists('autorizacion.usuarios');

        DB::statement('DROP SCHEMA IF EXISTS autorizacion CASCADE');
    }
};
