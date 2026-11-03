<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. entidads
        Schema::create('entidads', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 4)->nullable();
            $table->string('nombre')->unique();
            $table->string('abreviatura', 50)->unique();
            $table->integer('entidad_mayor');
            $table->string('observacion');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->softDeletes();
        });
        DB::statement('CREATE UNIQUE INDEX entidads_numero_unique ON entidads (numero) WHERE numero IS NOT NULL');
        DB::statement('ALTER TABLE entidads ADD CONSTRAINT entidads_entidad_mayor_check CHECK (entidad_mayor >= 0)');
        DB::statement('CREATE INDEX entidads_entidad_mayor_idx ON entidads (entidad_mayor)');

        // 2. plazas
        Schema::create('plazas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->foreignId('entidad_id')->constrained('entidads')->restrictOnDelete();
            $table->string('observacion');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->softDeletes();
            $table->unique(['nombre', 'entidad_id']);
        });

        // 3. verificadors
        Schema::create('verificadors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grado_mil_id')->constrained('grado_mils')->restrictOnDelete();
            $table->string('primer_nombre');
            $table->string('segundo_nombre')->nullable();
            $table->string('primer_apellido');
            $table->string('segundo_apellido');
            $table->string('carne', 11)->unique();
            $table->string('expediente', 6)->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->softDeletes();
        });
        DB::statement('ALTER TABLE verificadors ADD CONSTRAINT verificadors_carne_check CHECK (char_length(carne) = 11)');
        DB::statement('ALTER TABLE verificadors ADD CONSTRAINT verificadors_expediente_check CHECK (expediente IS NULL OR char_length(expediente) = 6)');

        // 4. trabajadors
        Schema::create('trabajadors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grado_mil_id')->constrained('grado_mils')->restrictOnDelete();
            $table->string('primer_nombre');
            $table->string('segundo_nombre')->nullable();
            $table->string('primer_apellido');
            $table->string('segundo_apellido');
            $table->string('natural_de');
            $table->string('carne', 11)->unique();
            $table->string('expediente', 6)->nullable()->unique();
            $table->string('direccion_particular');
            $table->string('direccion_residencia');
            $table->text('motivos');
            $table->boolean('disposicion_extranjero');
            $table->text('criterio');
            $table->text('juramento');
            $table->boolean('objetado')->nullable();
            $table->foreignId('escolaridad_id')->constrained('escolaridads')->restrictOnDelete();
            $table->foreignId('categoria_doc_id')->nullable()->constrained('categoria_docs')->restrictOnDelete();
            $table->foreignId('categoria_cien_id')->nullable()->constrained('categoria_ciens')->restrictOnDelete();
            $table->foreignId('categoria_trop_id')->nullable()->constrained('categoria_trops')->restrictOnDelete();
            $table->foreignId('categoria_acad_id')->nullable()->constrained('categoria_acads')->restrictOnDelete();
            $table->foreignId('registrador_id')->constrained('trabajadors')->restrictOnDelete();
            $table->date('fecha_captacion');
            $table->foreignId('verificador_id')->nullable()->constrained('verificadors')->restrictOnDelete();
            $table->date('fecha_verificacion')->nullable();
            $table->foreignId('aprobador_id')->nullable()->constrained('trabajadors')->restrictOnDelete();
            $table->date('fecha_aprobacion')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->softDeletes();
        });
        DB::statement('ALTER TABLE trabajadors ADD CONSTRAINT trabajadors_carne_check CHECK (char_length(carne) = 11)');
        DB::statement('ALTER TABLE trabajadors ADD CONSTRAINT trabajadors_expediente_check CHECK (expediente IS NULL OR char_length(expediente) = 6)');
        DB::statement('CREATE INDEX trabajadors_registrador_id_idx ON trabajadors (registrador_id)');
        DB::statement('CREATE INDEX trabajadors_aprobador_id_idx ON trabajadors (aprobador_id)');
        DB::statement('CREATE INDEX trabajadors_verificador_id_idx ON trabajadors (verificador_id)');

        // 5. cuentas
        Schema::create('cuentas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->unique()->constrained('trabajadors')->restrictOnDelete();
            $table->string('usuario')->unique();
            $table->string('password');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->softDeletes();
        });

        // 6. ocupacions
        Schema::create('ocupacions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadors')->restrictOnDelete();
            $table->foreignId('plaza_id')->constrained('plazas')->restrictOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->softDeletes();
        });
        DB::statement('ALTER TABLE ocupacions ADD CONSTRAINT ocupacions_fechas_check CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)');
        DB::statement('CREATE INDEX ocupacions_trabajador_id_idx ON ocupacions (trabajador_id)');
        DB::statement('CREATE INDEX ocupacions_plaza_id_idx ON ocupacions (plaza_id)');
        DB::statement('CREATE INDEX ocupacions_trabajador_activa_idx ON ocupacions (trabajador_id) WHERE fecha_fin IS NULL');
        DB::statement('CREATE INDEX ocupacions_plaza_activa_idx ON ocupacions (plaza_id) WHERE fecha_fin IS NULL');

        // 7. adscripcions
        Schema::create('adscripcions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verificador_id')->constrained('verificadors')->restrictOnDelete();
            $table->foreignId('entidad_id')->constrained('entidads')->restrictOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->softDeletes();
        });
        DB::statement('ALTER TABLE adscripcions ADD CONSTRAINT adscripcions_fechas_check CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)');
        DB::statement('CREATE INDEX adscripcions_verificador_activa_idx ON adscripcions (verificador_id) WHERE fecha_fin IS NULL');
        DB::statement('CREATE INDEX adscripcions_entidad_id_idx ON adscripcions (entidad_id)');

        // 8. delitos
        Schema::create('delitos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadors')->restrictOnDelete();
            $table->string('nombre');
            $table->text('sancion');
            $table->date('fecha');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->softDeletes();
        });
        DB::statement('CREATE INDEX delitos_trabajador_id_idx ON delitos (trabajador_id)');

        // 9. convivientes
        Schema::create('convivientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadors')->restrictOnDelete();
            $table->string('nombres_apellidos');
            $table->foreignId('parentesco_id')->constrained('parentescos')->restrictOnDelete();
            $table->string('centro_trabajo');
            $table->string('plaza');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->softDeletes();
        });
        DB::statement('CREATE INDEX convivientes_trabajador_id_idx ON convivientes (trabajador_id)');

        // 10. extranjeros
        Schema::create('extranjeros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadors')->restrictOnDelete();
            $table->string('nombres_apellidos')->nullable();
            $table->foreignId('parentesco_id')->constrained('parentescos')->restrictOnDelete();
            $table->foreignId('pais_id')->constrained('paiss')->restrictOnDelete();
            $table->boolean('mantiene_relacion');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->softDeletes();
        });
        DB::statement('CREATE INDEX extranjeros_trabajador_id_idx ON extranjeros (trabajador_id)');

        // 11. sancionados
        Schema::create('sancionados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadors')->restrictOnDelete();
            $table->string('nombres_apellidos')->nullable();
            $table->foreignId('parentesco_id')->constrained('parentescos')->restrictOnDelete();
            $table->text('delito');
            $table->text('sancion')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->softDeletes();
        });
        DB::statement('CREATE INDEX sancionados_trabajador_id_idx ON sancionados (trabajador_id)');

        // 12. academicos
        Schema::create('academicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadors')->restrictOnDelete();
            $table->foreignId('grado_acad_id')->constrained('grado_acads')->restrictOnDelete();
            $table->string('titulo');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->softDeletes();
            $table->unique(['trabajador_id', 'grado_acad_id']);
        });
        DB::statement('CREATE INDEX academicos_trabajador_id_idx ON academicos (trabajador_id)');

        // 13. cientificos
        Schema::create('cientificos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadors')->restrictOnDelete();
            $table->foreignId('grado_cien_id')->constrained('grado_ciens')->restrictOnDelete();
            $table->string('titulo');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->softDeletes();
            $table->unique(['trabajador_id', 'grado_cien_id']);
        });
        DB::statement('CREATE INDEX cientificos_trabajador_id_idx ON cientificos (trabajador_id)');

        // 14. acceso_trabajadors
        Schema::create('acceso_trabajadors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadors')->restrictOnDelete();
            $table->foreignId('acceso_id')->constrained('accesos')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->softDeletes();
            $table->unique(['trabajador_id', 'acceso_id']);
        });
        DB::statement('CREATE INDEX acceso_trabajadors_trabajador_id_idx ON acceso_trabajadors (trabajador_id)');

        // 15. militancia_trabajadors
        Schema::create('militancia_trabajadors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadors')->restrictOnDelete();
            $table->foreignId('militancia_id')->constrained('militancias')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->softDeletes();
            $table->unique(['trabajador_id', 'militancia_id']);
        });
        DB::statement('CREATE INDEX militancia_trabajadors_trabajador_id_idx ON militancia_trabajadors (trabajador_id)');

        // 16. organizacion_trabajadors
        Schema::create('organizacion_trabajadors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadors')->restrictOnDelete();
            $table->foreignId('organizacion_id')->constrained('organizacions')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->softDeletes();
            $table->unique(['trabajador_id', 'organizacion_id']);
        });
        DB::statement('CREATE INDEX organizacion_trabajadors_trabajador_id_idx ON organizacion_trabajadors (trabajador_id)');

        // 17. limitacion_trabajadors
        Schema::create('limitacion_trabajadors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadors')->restrictOnDelete();
            $table->foreignId('limitacion_id')->constrained('limitacions')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->softDeletes();
            $table->unique(['trabajador_id', 'limitacion_id']);
        });
        DB::statement('CREATE INDEX limitacion_trabajadors_trabajador_id_idx ON limitacion_trabajadors (trabajador_id)');

        // 18. militancia_convivientes
        Schema::create('militancia_convivientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conviviente_id')->constrained('convivientes')->restrictOnDelete();
            $table->foreignId('militancia_id')->constrained('militancias')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->softDeletes();
            $table->unique(['conviviente_id', 'militancia_id']);
        });
        DB::statement('CREATE INDEX militancia_convivientes_conviviente_id_idx ON militancia_convivientes (conviviente_id)');

        // 19. organizacion_convivientes
        Schema::create('organizacion_convivientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conviviente_id')->constrained('convivientes')->restrictOnDelete();
            $table->foreignId('organizacion_id')->constrained('organizacions')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->softDeletes();
            $table->unique(['conviviente_id', 'organizacion_id']);
        });
        DB::statement('CREATE INDEX organizacion_convivientes_conviviente_id_idx ON organizacion_convivientes (conviviente_id)');

        // 20. rol_trabajadors
        Schema::create('rol_trabajadors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadors')->restrictOnDelete();
            $table->foreignId('rol_id')->constrained('rols')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->softDeletes();
            $table->unique(['trabajador_id', 'rol_id']);
        });
        DB::statement('CREATE INDEX rol_trabajadors_trabajador_id_idx ON rol_trabajadors (trabajador_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('rol_trabajadors');
        Schema::dropIfExists('organizacion_convivientes');
        Schema::dropIfExists('militancia_convivientes');
        Schema::dropIfExists('limitacion_trabajadors');
        Schema::dropIfExists('organizacion_trabajadors');
        Schema::dropIfExists('militancia_trabajadors');
        Schema::dropIfExists('acceso_trabajadors');
        Schema::dropIfExists('cientificos');
        Schema::dropIfExists('academicos');
        Schema::dropIfExists('sancionados');
        Schema::dropIfExists('extranjeros');
        Schema::dropIfExists('convivientes');
        Schema::dropIfExists('delitos');
        Schema::dropIfExists('adscripcions');
        Schema::dropIfExists('ocupacions');
        Schema::dropIfExists('cuentas');
        Schema::dropIfExists('trabajadors');
        Schema::dropIfExists('verificadors');
        Schema::dropIfExists('plazas');
        Schema::dropIfExists('entidads');
    }
};