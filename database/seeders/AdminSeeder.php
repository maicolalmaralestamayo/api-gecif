<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // 1. autorizacion.modos
        foreach ([
            ['nombre' => 'todo',         'descripcion' => 'Acceso a todos'],
            ['nombre' => 'todo_excepto', 'descripcion' => 'Todos excepto los listados'],
            ['nombre' => 'nada',         'descripcion' => 'Sin acceso'],
            ['nombre' => 'nada_excepto', 'descripcion' => 'Solo los listados'],
        ] as $modo) {
            DB::table('autorizacion.modos')->updateOrInsert(
                ['nombre' => $modo['nombre']],
                $modo + ['created_at' => $now, 'updated_at' => $now]
            );
        }

        // 2. autorizacion.acciones
        foreach ([
            ['nombre' => 'post',   'descripcion' => 'Crear un registro',                  'permite_campos' => true],
            ['nombre' => 'get',    'descripcion' => 'Consultar registros',                'permite_campos' => true],
            ['nombre' => 'put',    'descripcion' => 'Actualizar completamente registros', 'permite_campos' => false],
            ['nombre' => 'patch',  'descripcion' => 'Actualizar parcialmente registros',  'permite_campos' => true],
            ['nombre' => 'delete', 'descripcion' => 'Eliminar registros',                 'permite_campos' => false],
        ] as $accion) {
            DB::table('autorizacion.acciones')->updateOrInsert(
                ['nombre' => $accion['nombre']],
                $accion + ['created_at' => $now, 'updated_at' => $now]
            );
        }

        // 3. autorizacion.rols
        DB::table('autorizacion.rols')->updateOrInsert(
            ['nombre' => 'admin'],
            ['display_name' => 'Administrador', 'created_at' => $now, 'updated_at' => $now]
        );
        $rolId = DB::table('autorizacion.rols')->where('nombre', 'admin')->value('id');

        // 4. autorizacion.usuarios
        DB::table('autorizacion.usuarios')->updateOrInsert(
            ['nombre' => 'administrador'],
            ['activo' => true, 'created_at' => $now, 'updated_at' => $now]
        );
        $usuarioId = DB::table('autorizacion.usuarios')->where('nombre', 'administrador')->value('id');

        // 5. autorizacion.rol_usuario
        DB::table('autorizacion.rol_usuario')->updateOrInsert(
            ['rol_id' => $rolId, 'usuario_id' => $usuarioId],
            ['created_at' => $now, 'updated_at' => $now]
        );

        // 6. autorizacion.permisos  (todo sobre todo)
        $modoTodoId = DB::table('autorizacion.modos')->where('nombre', 'todo')->value('id');

        DB::table('autorizacion.permisos')->updateOrInsert(
            ['rol_id' => $rolId],
            [
                'modo_accion_id'  => $modoTodoId,
                'modo_recurso_id' => $modoTodoId,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]
        );
        $permisoId = DB::table('autorizacion.permisos')->where('rol_id', $rolId)->value('id');

        // 7. autorizacion.permisos_campo  (todos los campos)
        DB::table('autorizacion.permisos_campo')->updateOrInsert(
            ['permiso_id' => $permisoId],
            ['modo_campo_id' => $modoTodoId, 'created_at' => $now, 'updated_at' => $now]
        );

        $this->command->info('Usuario [administrador] creado, activado y con todos los permisos.');
    }
}