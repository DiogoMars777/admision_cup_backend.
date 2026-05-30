<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('super_administrador')->truncate();
        DB::table('usuario')->truncate();
        DB::table('rol')->truncate();
        DB::table('persona')->truncate();
        Schema::enableForeignKeyConstraints();

        // 1. Crear Rol
        $rolId = DB::table('rol')->insertGetId([
            'nombre' => 'Super Administrador',
            'descripcion' => 'Administrador principal del sistema',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Crear Persona
        $personaId = DB::table('persona')->insertGetId([
            'ci' => '12345678',
            'nombre' => 'Diogo Mars',
            'sexo' => 'M',
            'telefono' => '70000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Crear Registro Herencia Super Administrador
        DB::table('super_administrador')->insert([
            'id_persona' => $personaId,
            'cargo' => 'Gerente de Sistemas',
            'estado' => 'Activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Crear Usuario con la contraseña encriptada y el correo especificado
        DB::table('usuario')->insert([
            'id_persona' => $personaId,
            'id_rol' => $rolId,
            'email' => 'diogomars2020@gmail.com',
            'password' => Hash::make('admin123'),
            'estado' => 'Activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Seeder ejecutado: Usuario Super Admin creado correctamente.');
    }
}
