<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $jsonFilePath = base_path('db_dump.json');
        
        if (!File::exists($jsonFilePath)) {
            $this->command->error("No se encontró el archivo db_dump.json. Por favor asegúrate de que exista en la raíz del proyecto.");
            return;
        }

        $json = File::get($jsonFilePath);
        $data = json_decode($json, true);

        if (!$data) {
            $this->command->error("El archivo db_dump.json no es válido.");
            return;
        }

        $this->command->info("Restaurando datos desde db_dump.json...");

        // Desactivar restricciones de llaves foráneas temporalmente (MySQL/PostgreSQL compatible approach if needed, 
        // but since we insert in order, it should be fine. For PostgreSQL we can use DB::statement).
        // Since we are inserting in the correct order (the JSON was dumped in order), it usually works.
        // Let's ensure the order is correct just in case.
        $tablesOrder = [
            'rol', 'persona', 'usuario', 'carrera', 'materia', 'aula', 
            'gestion_cup', 'gestion_academica', 
            'aspirante_docente', 'postulacion_docente',
            'docente', 'docente_materia', 
            'grupo', 'grupo_materia', 'postulante', 'postulante_carrera', 'postulante_grupo', 
            'evaluacion', 'programacion_evaluacion',
            'requisito', 'materia_requisito', 'docente_requisito', 'postulante_requisito', 'comprobante', 'pago'
        ];

        foreach ($tablesOrder as $table) {
            if (isset($data[$table]) && count($data[$table]) > 0) {
                // Laravel's insert might fail if the chunk is too big, but for our size it's fine.
                // We truncate first just in case
                // DB::table($table)->truncate(); // Handled by migrate:fresh
                
                DB::table($table)->delete();
                DB::table($table)->insert($data[$table]);
                $this->command->info("Tabla {$table} restaurada con " . count($data[$table]) . " registros.");
                
                // Fix PostgreSQL sequences so auto-increment works for new inserts
                if (config('database.default') === 'pgsql' && Schema::hasColumn($table, 'id')) {
                    DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), coalesce(max(id), 1), max(id) IS NOT null) FROM {$table};");
                }
            }
        }

        $this->command->info("¡Base de datos restaurada exitosamente!");
    }
}
