<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Faker\Factory as Faker;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_ES');

        // ═══════════════════════════════════════════════════════════
        // 1. ROLES
        // ═══════════════════════════════════════════════════════════
        $rolPostulanteId = DB::table('rol')->where('nombre', 'Postulante')->value('id');
        if (!$rolPostulanteId) {
            $rolPostulanteId = DB::table('rol')->insertGetId([
                'nombre' => 'Postulante',
                'descripcion' => 'Postulante al CUP',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rolDocenteId = DB::table('rol')->where('nombre', 'Docente')->value('id');
        if (!$rolDocenteId) {
            $rolDocenteId = DB::table('rol')->insertGetId([
                'nombre' => 'Docente',
                'descripcion' => 'Docente del CUP',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rolAdminId = DB::table('rol')->where('nombre', 'Administrativo')->value('id');
        if (!$rolAdminId) {
            $rolAdminId = DB::table('rol')->insertGetId([
                'nombre' => 'Administrativo',
                'descripcion' => 'Personal administrativo del CUP',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ═══════════════════════════════════════════════════════════
        // 2. CARRERAS
        // ═══════════════════════════════════════════════════════════
        $carrerasNombres = [
            'Ingeniería de Sistemas', 'Ingeniería Informática', 'Medicina',
            'Derecho', 'Arquitectura', 'Contaduría Pública',
        ];
        $carreraIds = [];
        foreach ($carrerasNombres as $carrera) {
            $id = DB::table('carrera')->where('nombre', $carrera)->value('id');
            if (!$id) {
                $id = DB::table('carrera')->insertGetId([
                    'nombre' => $carrera,
                    'descripcion' => 'Carrera de ' . $carrera,
                    'estado' => 'Activo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $carreraIds[$carrera] = $id;
        }

        // ═══════════════════════════════════════════════════════════
        // 3. MODALIDADES + MODALIDAD_CARRERA (clase intermedia)
        // ═══════════════════════════════════════════════════════════
        $modalidadesNombres = ['Presencial', 'Virtual', 'Semi-Presencial'];
        $modalidadIds = [];
        foreach ($modalidadesNombres as $mod) {
            $id = DB::table('modalidad')->where('nombre', $mod)->value('id');
            if (!$id) {
                $id = DB::table('modalidad')->insertGetId([
                    'nombre' => $mod,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $modalidadIds[$mod] = $id;
        }

        // Vincular cada carrera con sus modalidades disponibles en modalidad_carrera
        foreach ($carreraIds as $carreraNombre => $carreraId) {
            foreach ($modalidadIds as $modNombre => $modId) {
                $existe = DB::table('modalidad_carrera')
                    ->where('id_carrera', $carreraId)
                    ->where('id_modalidad', $modId)
                    ->exists();
                if (!$existe) {
                    DB::table('modalidad_carrera')->insert([
                        'id_carrera' => $carreraId,
                        'id_modalidad' => $modId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // ═══════════════════════════════════════════════════════════
        // 4. ESPECIALIDADES
        // ═══════════════════════════════════════════════════════════
        $especialidades = ['Matemáticas', 'Ciencias Naturales', 'Ciencias Sociales', 'Lenguaje y Literatura', 'Tecnología'];
        foreach ($especialidades as $esp) {
            DB::table('especialidad')->updateOrInsert(['nombre' => $esp], [
                'nombre' => $esp,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ═══════════════════════════════════════════════════════════
        // 5. MATERIAS
        // ═══════════════════════════════════════════════════════════
        $materias = ['Matemáticas', 'Física', 'Química', 'Lenguaje', 'Historia', 'Biología', 'Geografía', 'Cívica', 'Filosofía', 'Computación'];
        $materiaIds = [];
        foreach ($materias as $materia) {
            $id = DB::table('materia')->where('nombre', $materia)->value('id');
            if (!$id) {
                $id = DB::table('materia')->insertGetId([
                    'nombre' => $materia,
                    'descripcion' => 'Materia de ' . $materia,
                    'estado' => 'Activo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $materiaIds[$materia] = $id;
        }

        // ═══════════════════════════════════════════════════════════
        // 6. AULAS
        // ═══════════════════════════════════════════════════════════
        $aulaIds = [];
        for ($i = 1; $i <= 10; $i++) {
            $nroAula = 'A-' . str_pad($i, 3, '0', STR_PAD_LEFT);
            $id = DB::table('aula')->where('aula_nro', $nroAula)->value('id');
            if (!$id) {
                $id = DB::table('aula')->insertGetId([
                    'aula_nro' => $nroAula,
                    'capacidad' => $faker->numberBetween(30, 60),
                    'tipo_aula' => $faker->randomElement(['Teórica', 'Laboratorio']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $aulaIds[] = $id;
        }

        // ═══════════════════════════════════════════════════════════
        // 7. GESTIÓN ACADÉMICA
        // ═══════════════════════════════════════════════════════════
        $gestionId = DB::table('gestion_academica')->where('nombre', 'Gestión 2026')->value('id');
        if (!$gestionId) {
            $gestionId = DB::table('gestion_academica')->insertGetId([
                'nombre' => 'Gestión 2026',
                'año' => 2026,
                'periodo' => '1',
                'fecha_ini' => '2026-02-01',
                'fecha_fin' => '2026-06-30',
                'estado' => 'Activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ═══════════════════════════════════════════════════════════
        // 8. CUPO_CARRERA (Cupos por carrera en la gestión)
        // ═══════════════════════════════════════════════════════════
        foreach ($carreraIds as $carreraNombre => $carreraId) {
            $existeCupo = DB::table('cupo_carrera')
                ->where('id_carrera', $carreraId)
                ->where('id_gestionacademica', $gestionId)
                ->exists();
            if (!$existeCupo) {
                $cupoMax = $faker->numberBetween(40, 100);
                DB::table('cupo_carrera')->insert([
                    'id_carrera' => $carreraId,
                    'id_gestionacademica' => $gestionId,
                    'cupo_max' => $cupoMax,
                    'cupo_disp' => $faker->numberBetween(10, $cupoMax),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // ═══════════════════════════════════════════════════════════
        // 9. GRUPOS
        // ═══════════════════════════════════════════════════════════
        $grupoIds = [];
        $turnos = ['Mañana', 'Tarde', 'Noche'];
        for ($i = 1; $i <= 10; $i++) {
            $nombreGrupo = 'Grupo ' . str_pad($i, 2, '0', STR_PAD_LEFT);
            $id = DB::table('grupo')->where('nombre', $nombreGrupo)->value('id');
            if (!$id) {
                $id = DB::table('grupo')->insertGetId([
                    'id_gestionacademica' => $gestionId,
                    'nombre' => $nombreGrupo,
                    'cupo_max' => 50,
                    'cant_estudiante' => $faker->numberBetween(10, 45),
                    'modalidad' => $faker->randomElement($modalidadesNombres),
                    'turno' => $faker->randomElement($turnos),
                    'estado' => 'Activo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $grupoIds[] = $id;
        }

        // ═══════════════════════════════════════════════════════════
        // 10. POSTULANTES (10) con turno_preferido, modalidad_preferida
        //     + postulante_carrera + postulante_grupo
        // ═══════════════════════════════════════════════════════════
        $postulanteIds = [];
        $carrerasArray = array_values($carreraIds);
        $carrerasNombresArray = array_keys($carreraIds);
        $modalidadesArray = array_values($modalidadIds);

        for ($i = 0; $i < 10; $i++) {
            $personaId = DB::table('persona')->insertGetId([
                'ci' => $faker->unique()->randomNumber(8, true),
                'nombre' => $faker->name,
                'sexo' => $faker->randomElement(['M', 'F']),
                'telefono' => $faker->phoneNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $turnoPreferido = $faker->randomElement($turnos);
            $modalidadPreferida = $faker->randomElement($modalidadesNombres);

            DB::table('postulante')->insert([
                'id_persona' => $personaId,
                'fecha_nac' => $faker->dateTimeBetween('-25 years', '-17 years')->format('Y-m-d'),
                'direccion' => $faker->address,
                'colegio' => 'Colegio ' . $faker->company,
                'turno_preferido' => $turnoPreferido,
                'modalidad_preferida' => $modalidadPreferida,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Carrera 1 (obligatoria para todos)
            $c1Index = $faker->numberBetween(0, count($carrerasArray) - 1);
            $m1Index = $faker->numberBetween(0, count($modalidadesArray) - 1);
            DB::table('postulante_carrera')->insert([
                'id_postulante' => $personaId,
                'id_carrera' => $carrerasArray[$c1Index],
                'id_modalidad' => $modalidadesArray[$m1Index],
                'prioridad' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Carrera 2 (opcional, 60% de probabilidad)
            if ($faker->boolean(60)) {
                $c2Index = $faker->numberBetween(0, count($carrerasArray) - 1);
                // Asegurar que sea diferente a carrera 1
                while ($c2Index === $c1Index) {
                    $c2Index = $faker->numberBetween(0, count($carrerasArray) - 1);
                }
                $m2Index = $faker->numberBetween(0, count($modalidadesArray) - 1);
                DB::table('postulante_carrera')->insert([
                    'id_postulante' => $personaId,
                    'id_carrera' => $carrerasArray[$c2Index],
                    'id_modalidad' => $modalidadesArray[$m2Index],
                    'prioridad' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Asignar postulante a un grupo
            $grupoAsignado = $grupoIds[$i % count($grupoIds)];
            DB::table('postulante_grupo')->insert([
                'id_postulante' => $personaId,
                'id_grupo' => $grupoAsignado,
                'fecha_asignacion' => now()->format('Y-m-d'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('usuario')->insert([
                'id_persona' => $personaId,
                'id_rol' => $rolPostulanteId,
                'email' => "postulante{$i}@cup.edu.bo",
                'password' => Hash::make('password123'),
                'estado' => 'Activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $postulanteIds[] = $personaId;
        }

        // ═══════════════════════════════════════════════════════════
        // 11. DOCENTES (10) + docente_materia + docente_especialidad
        // ═══════════════════════════════════════════════════════════
        $docenteIds = [];
        $especialidadIds = DB::table('especialidad')->pluck('id')->toArray();
        $materiaIdsArray = array_values($materiaIds);

        for ($i = 0; $i < 10; $i++) {
            $personaId = DB::table('persona')->insertGetId([
                'ci' => $faker->unique()->randomNumber(8, true),
                'nombre' => $faker->name,
                'sexo' => $faker->randomElement(['M', 'F']),
                'telefono' => $faker->phoneNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('docente')->insert([
                'id_persona' => $personaId,
                'grado_academico' => $faker->randomElement(['Licenciatura', 'Maestría', 'Doctorado']),
                'experiencia_docente' => $faker->numberBetween(1, 20),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Asignar 1-2 materias al docente
            $materiasDocente = $faker->randomElements($materiaIdsArray, $faker->numberBetween(1, 2));
            foreach ($materiasDocente as $matId) {
                DB::table('docente_materia')->updateOrInsert(
                    ['id_docente' => $personaId, 'id_materia' => $matId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }

            // Asignar 1 especialidad al docente
            if (count($especialidadIds) > 0) {
                $espId = $faker->randomElement($especialidadIds);
                DB::table('docente_especialidad')->updateOrInsert(
                    ['id_docente' => $personaId, 'id_especialidad' => $espId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }

            DB::table('usuario')->insert([
                'id_persona' => $personaId,
                'id_rol' => $rolDocenteId,
                'email' => "docente{$i}@cup.edu.bo",
                'password' => Hash::make('password123'),
                'estado' => 'Activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $docenteIds[] = $personaId;
        }

        // ═══════════════════════════════════════════════════════════
        // 12. HORARIOS (asignar docente-materia-aula-grupo)
        // ═══════════════════════════════════════════════════════════
        $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
        $horas = [
            ['08:00', '09:30'], ['09:45', '11:15'], ['11:30', '13:00'],
            ['14:00', '15:30'], ['15:45', '17:15'], ['18:00', '19:30'],
        ];

        for ($i = 0; $i < 15; $i++) {
            $docenteId = $faker->randomElement($docenteIds);
            $materiaId = $faker->randomElement($materiaIdsArray);
            $aulaId = $faker->randomElement($aulaIds);
            $grupoId = $faker->randomElement($grupoIds);
            $hora = $faker->randomElement($horas);

            DB::table('horario')->insert([
                'id_grupo' => $grupoId,
                'id_docente' => $docenteId,
                'id_materia' => $materiaId,
                'id_aula' => $aulaId,
                'dia' => $faker->randomElement($dias),
                'hora_ini' => $hora[0],
                'hora_fin' => $hora[1],
                'modalidad' => $faker->randomElement($modalidadesNombres),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ═══════════════════════════════════════════════════════════
        // 13. REQUISITOS + POSTULANTE_REQUISITO
        // ═══════════════════════════════════════════════════════════
        $reqsBase = [
            ['nombre' => 'Fotocopia de CI', 'desc' => 'Documento de identidad legible'],
            ['nombre' => 'Título Bachiller', 'desc' => 'Título legalizado'],
            ['nombre' => 'Certificado de nacimiento', 'desc' => 'Original y actualizado'],
            ['nombre' => 'Fotografía actualizada', 'desc' => 'Fondo rojo 4x4'],
            ['nombre' => 'Formulario de inscripción', 'desc' => 'Firmado por el postulante'],
        ];

        $adminPersonaId = DB::table('super_administrador')->value('id_persona') ?? 1;

        $reqIds = [];
        foreach ($reqsBase as $req) {
            $reqId = DB::table('requisito')->insertGetId([
                'id_abministrador' => $adminPersonaId,
                'nombre' => $req['nombre'],
                'descripcion' => $req['desc'],
                'estado' => 'Activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $reqIds[] = $reqId;
        }

        // Asignar requisitos a los postulantes con estados variados
        foreach ($postulanteIds as $index => $postId) {
            foreach ($reqIds as $rIndex => $reqId) {
                $estado = 'Pendiente';
                $observacion = '';

                if ($index === 0) {
                    $estado = 'Entregado';
                } else if ($index === 1) {
                    if ($rIndex < 3) $estado = 'Entregado';
                    else if ($rIndex == 3) { $estado = 'Pendiente'; $observacion = 'Falta firmar'; }
                } else {
                    $rand = rand(0, 2);
                    if ($rand == 0) $estado = 'Entregado';
                    else if ($rand == 1) { $estado = 'Pendiente'; $observacion = 'Documento ilegible'; }
                }

                DB::table('postulante_requisito')->insert([
                    'id_postulante' => $postId,
                    'id_requisito' => $reqId,
                    'fecha_asignacion' => now()->format('Y-m-d'),
                    'estado' => $estado,
                    'observacion' => $observacion,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // ═══════════════════════════════════════════════════════════
        // 14. COMPROBANTES + PAGOS
        // ═══════════════════════════════════════════════════════════
        for ($i = 0; $i < 5; $i++) {
            $comprobanteId = DB::table('comprobante')->insertGetId([
                'nro_comprobante' => 'COMP-' . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
                'fecha_emision' => $faker->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('pago')->insert([
                'id_postulante' => $postulanteIds[$i],
                'id_comprobante' => $comprobanteId,
                'monto' => $faker->randomElement([350.00, 500.00, 750.00]),
                'metodo_pago' => $faker->randomElement(['Efectivo', 'Transferencia', 'QR']),
                'codigo_transaccion' => 'TXN-' . strtoupper($faker->bothify('??###')),
                'estado' => 'Procesado',
                'fecha' => now()->format('Y-m-d'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ═══════════════════════════════════════════════════════════
        // 15. EVALUACIONES + NOTAS
        // ═══════════════════════════════════════════════════════════
        $evaluacionIds = [];
        $materiasEval = array_slice($materiaIdsArray, 0, 5);
        foreach ($materiasEval as $matId) {
            $evalId = DB::table('evaluacion')->insertGetId([
                'id_materia' => $matId,
                'id_gestionacademica' => $gestionId,
                'nombre_eva' => 'Examen Parcial',
                'puntaje_max' => 100.00,
                'fecha' => $faker->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $evaluacionIds[] = $evalId;
        }

        // Asignar notas a los primeros 5 postulantes
        for ($i = 0; $i < min(5, count($postulanteIds)); $i++) {
            foreach ($evaluacionIds as $evalId) {
                DB::table('nota')->insert([
                    'id_postulante' => $postulanteIds[$i],
                    'id_evaluacion' => $evalId,
                    'puntaje_obtenido' => $faker->randomFloat(2, 30, 100),
                    'estado' => $faker->randomElement(['Aprobado', 'Reprobado']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // ═══════════════════════════════════════════════════════════
        // 16. MATERIA_REQUISITO
        // ═══════════════════════════════════════════════════════════
        $materiasReq = array_slice($materiaIdsArray, 0, 3);
        foreach ($materiasReq as $idx => $matId) {
            if (isset($reqIds[$idx])) {
                DB::table('materia_requisito')->updateOrInsert(
                    ['id_materia' => $matId, 'id_requisito' => $reqIds[$idx]],
                    [
                        'obligatorio' => true,
                        'estado' => 'Activo',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        $this->command->info('✅ DemoDataSeeder completo: Roles, Carreras, Modalidades, Modalidad_Carrera, Postulantes (con turno/modalidad/carreras), Docentes, Grupos, Horarios, Requisitos, Pagos, Evaluaciones y Notas.');
    }
}
