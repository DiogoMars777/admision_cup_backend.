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
        $rolesNombres = ['Super Admin', 'Administrador', 'Coordinador', 'Docente', 'Postulante'];
        foreach ($rolesNombres as $rolNombre) {
            $id = DB::table('rol')->where('nombre', $rolNombre)->value('id');
            if (!$id) {
                DB::table('rol')->insert([
                    'nombre' => $rolNombre,
                    'descripcion' => $rolNombre,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $rolPostulanteId = DB::table('rol')->where('nombre', 'Postulante')->value('id');
        $rolDocenteId = DB::table('rol')->where('nombre', 'Docente')->value('id');
        $rolAdminId = DB::table('rol')->where('nombre', 'Administrador')->value('id');
        $rolSuperAdminId = DB::table('rol')->where('nombre', 'Super Admin')->value('id');

        // ═══════════════════════════════════════════════════════════
        // 1.5. SUPER ADMINISTRADOR
        // ═══════════════════════════════════════════════════════════
        $superAdminPersonaId = DB::table('persona')->where('correo', 'diogomars2020@gmail.com')->value('id');
        if (!$superAdminPersonaId) {
            $superAdminPersonaId = DB::table('persona')->insertGetId([
                'ci' => '12345678',
                'nombre' => 'Diogo Mars',
                'sexo' => 'M',
                'telefono' => '70000000',
                'correo' => 'diogomars2020@gmail.com',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('super_administrador')->insert([
                'id_persona' => $superAdminPersonaId,
                'cargo' => 'Gerente de Sistemas',
                'estado' => 'Activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('administrativo')->insert([
                'id_persona' => $superAdminPersonaId,
                'cargo' => 'Gerente de Sistemas',
                'estado' => 'Activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('usuario')->insert([
                'id_persona' => $superAdminPersonaId,
                'id_rol' => $rolSuperAdminId,
                'email' => 'diogomars2020@gmail.com',
                'password' => Hash::make('admin123'),
                'estado' => 'Activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ═══════════════════════════════════════════════════════════
        // 2. CARRERAS
        // ═══════════════════════════════════════════════════════════
        $carrerasNombres = [
            'Ingeniería en Sistemas', 'Ingeniería en Informática', 
            'Ingeniería en Redes y Telecomunicaciones', 'Ingeniería en Robótica'
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
        $modalidadesNombres = ['Presencial', 'Virtual'];
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
        $materias = ['Matemática', 'Física', 'Computación', 'Inglés'];
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
        for ($i = 1; $i <= 30; $i++) {
            $nroAula = 'A-' . str_pad($i, 3, '0', STR_PAD_LEFT);
            $id = DB::table('aula')->where('aula_nro', $nroAula)->value('id');
            if (!$id) {
                $id = DB::table('aula')->insertGetId([
                    'aula_nro' => $nroAula,
                    'capacidad' => 70,
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
        $gestionCupId = DB::table('gestion_cup')->where('nombre', 'CUP 2')->value('id');
        if (!$gestionCupId) {
            $gestionCupId = DB::table('gestion_cup')->insertGetId([
                'nombre' => 'CUP 2',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $gestionId = DB::table('gestion_academica')->where('nombre', 'Gestion CUP 2 2026')->value('id');
        if (!$gestionId) {
            $gestionId = DB::table('gestion_academica')->insertGetId([
                'nombre' => 'Gestion CUP 2 2026',
                'año' => 2026,
                'id_gestion_cup' => $gestionCupId,
                'fecha_ini' => '2026-08-01',
                'fecha_fin' => '2026-10-31',
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
                $cupoMax = 100;
                if (str_contains(strtolower($carreraNombre), 'sistemas')) $cupoMax = 100;
                elseif (str_contains(strtolower($carreraNombre), 'informática')) $cupoMax = 130;
                elseif (str_contains(strtolower($carreraNombre), 'redes')) $cupoMax = 150;
                elseif (str_contains(strtolower($carreraNombre), 'robótica')) $cupoMax = 150;
                
                DB::table('cupo_carrera')->insert([
                    'id_carrera' => $carreraId,
                    'id_gestionacademica' => $gestionId,
                    'cupo_max' => $cupoMax,
                    'cupo_disp' => $cupoMax,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }


        // ═══════════════════════════════════════════════════════════
        // 10. POSTULANTES (10) con turno_preferido, modalidad_preferida
        //     + postulante_carrera + postulante_grupo
        // ═══════════════════════════════════════════════════════════
        $postulanteIds = [];
        $carrerasArray = array_values($carreraIds);
        $carrerasNombresArray = array_keys($carreraIds);
        $modalidadesArray = array_values($modalidadIds);
        $turnos = ['Mañana', 'Tarde', 'Noche'];

        for ($i = 0; $i < 500; $i++) {
            $ci = str_pad(10000000 + $i, 8, '0', STR_PAD_LEFT);
            $correo = "postulante{$i}@cup.edu.bo";
            $personaId = DB::table('persona')->insertGetId([
                'ci' => $ci,
                'nombre' => $faker->name,
                'sexo' => $faker->randomElement(['M', 'F']),
                'telefono' => $faker->phoneNumber,
                'correo' => $correo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $turnoPreferido = $faker->randomElement($turnos);
            $modalidadPreferida = $faker->randomElement($modalidadesNombres);

            DB::table('postulante')->insert([
                'id_persona' => $personaId,
                'id_gestionacademica' => $gestionId,
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



            if ($i < 499) {
                DB::table('usuario')->insert([
                    'id_persona' => $personaId,
                    'id_rol' => $rolPostulanteId,
                    'email' => $correo,
                    'password' => Hash::make($ci, ['rounds' => 4]),
                    'estado' => 'Activo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $postulanteIds[] = $personaId;
        }

        // ═══════════════════════════════════════════════════════════
        // 11. DOCENTES (15) desde Aspirantes + Postulaciones
        // ═══════════════════════════════════════════════════════════
        $docenteIds = [];
        $especialidadIds = DB::table('especialidad')->pluck('id')->toArray();
        $materiaIdsArray = array_values($materiaIds);

        for ($i = 1; $i <= 15; $i++) {
            $ci = $faker->unique()->randomNumber(8, true);
            $correo = "docente{$i}@cup.edu.bo";
            $personaId = DB::table('persona')->insertGetId([
                'ci' => $ci,
                'nombre' => $faker->name,
                'sexo' => $faker->randomElement(['M', 'F']),
                'telefono' => $faker->phoneNumber,
                'correo' => $correo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Crear como aspirante primero
            DB::table('aspirante_docente')->insert([
                'id_persona' => $personaId,
                'fecha_registro' => now(),
                'grado_academico' => $faker->randomElement(['Licenciatura', 'Maestría', 'Doctorado']),
                'experiencia' => $faker->numberBetween(1, 20),
                'estado' => 'Activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Asignar de 1 a 3 postulaciones (materias)
            $materiasPostuladas = $faker->randomElements($materiaIdsArray, $faker->numberBetween(1, min(3, count($materiaIdsArray))));
            foreach ($materiasPostuladas as $matId) {
                DB::table('postulacion_docente')->insert([
                    'id_aspirante_docente' => $personaId,
                    'id_materia' => $matId,
                    'fecha_postulacion' => now(),
                    'estado' => 'Aprobado',
                    'observacion' => 'Aprobado automáticamente en seeder',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Convertirlo a Docente oficial
            DB::table('docente')->insert([
                'id_persona' => $personaId,
                'grado_academico' => $faker->randomElement(['Licenciatura', 'Maestría', 'Doctorado']),
                'experiencia_docente' => $faker->numberBetween(1, 20),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Asignarle en docente_materia las mismas materias que postuló
            foreach ($materiasPostuladas as $matId) {
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
                'email' => $correo,
                'password' => Hash::make($ci, ['rounds' => 4]),
                'estado' => 'Activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $docenteIds[] = $personaId;
        }


        // ═══════════════════════════════════════════════════════════
        // 13. REQUISITOS + POSTULANTE_REQUISITO
        // ═══════════════════════════════════════════════════════════
        $reqsBase = [
            // Postulantes
            ['nombre' => 'Original y copia del título de bachiller', 'desc' => 'Título de bachiller', 'tipo' => 'Postulante'],
            ['nombre' => 'Fotocopia del carnet de identidad', 'desc' => 'Documento de identidad legible', 'tipo' => 'Postulante'],
            ['nombre' => 'Formulario de preinscripción', 'desc' => 'Formulario de preinscripción', 'tipo' => 'Postulante'],
            ['nombre' => 'Comprobante de pago', 'desc' => 'Comprobante de pago original', 'tipo' => 'Postulante'],
            ['nombre' => 'Libreta o certificado de último año de secundaria', 'desc' => 'Libreta o certificado de notas', 'tipo' => 'Postulante'],
            
            // Materias Específicos
            ['nombre' => 'Título relacionado con Matemática o áreas afines', 'desc' => 'Matemática', 'tipo' => 'Materia', 'materia_vinculada' => 'Matemática'],
            ['nombre' => 'Certificado de capacitación en Matemática', 'desc' => 'Matemática', 'tipo' => 'Materia', 'materia_vinculada' => 'Matemática'],
            ['nombre' => 'Título relacionado con Física o áreas afines', 'desc' => 'Física', 'tipo' => 'Materia', 'materia_vinculada' => 'Física'],
            ['nombre' => 'Certificado de capacitación en Física', 'desc' => 'Física', 'tipo' => 'Materia', 'materia_vinculada' => 'Física'],
            ['nombre' => 'Título relacionado con Informática, Sistemas o Computación', 'desc' => 'Computación', 'tipo' => 'Materia', 'materia_vinculada' => 'Computación'],
            ['nombre' => 'Certificado de capacitación en Computación', 'desc' => 'Computación', 'tipo' => 'Materia', 'materia_vinculada' => 'Computación'],
            ['nombre' => 'Título o certificación en Inglés', 'desc' => 'Inglés', 'tipo' => 'Materia', 'materia_vinculada' => 'Inglés'],
            ['nombre' => 'Certificado de nivel de Inglés', 'desc' => 'Inglés', 'tipo' => 'Materia', 'materia_vinculada' => 'Inglés'],
            
            // Requisitos generales de materia
            ['nombre' => 'Experiencia mínima de 3 años', 'desc' => 'General', 'tipo' => 'Materia', 'materia_vinculada' => 'Todas'],
            ['nombre' => 'Curriculum Vitae', 'desc' => 'General', 'tipo' => 'Materia', 'materia_vinculada' => 'Todas'],
            ['nombre' => 'Fotocopia de C.I.', 'desc' => 'General', 'tipo' => 'Materia', 'materia_vinculada' => 'Todas'],
            ['nombre' => 'Certificado de estudios superiores', 'desc' => 'General', 'tipo' => 'Materia', 'materia_vinculada' => 'Todas'],
        ];

        $adminPersonaId = DB::table('super_administrador')->value('id_persona') ?? 1;

        $reqPostulanteIds = [];
        $reqDocenteIds = [];
        $reqMateriaIds = [];
        $reqMateriaMappings = []; // Guarda a qué materias corresponde cada id

        foreach ($reqsBase as $req) {
            $reqId = DB::table('requisito')->insertGetId([
                'id_abministrador' => $adminPersonaId,
                'nombre' => $req['nombre'],
                'descripcion' => $req['desc'],
                'tipo_requisito' => $req['tipo'],
                'estado' => 'Activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            if ($req['tipo'] === 'Postulante') {
                $reqPostulanteIds[] = $reqId;
            } elseif ($req['tipo'] === 'Docente') {
                $reqDocenteIds[] = $reqId;
            } else {
                $reqMateriaIds[] = $reqId;
                if (isset($req['materia_vinculada'])) {
                    $reqMateriaMappings[$reqId] = $req['materia_vinculada'];
                }
            }
        }

        // Asignar requisitos a los postulantes con estados variados
        foreach ($postulanteIds as $index => $postId) {
            foreach ($reqPostulanteIds as $rIndex => $reqId) {
                $estado = 'Pendiente';
                $observacion = '';

                if ($index < 499) {
                    // Los primeros 499 tienen todo completo (incluyendo su pago)
                    $estado = 'Entregado';
                    $observacion = '';
                } else {
                    // Los últimos 1 no han entregado documentos (o están observados)
                    $estado = 'Pendiente';
                    $observacion = 'Documento faltante o ilegible';
                }

                DB::table('postulante_requisito')->insert([
                    'id_postulante' => $postId,
                    'id_requisito' => $reqId,
                    'fecha_asignacion' => now()->format('Y-m-d'),
                    'estado' => $estado,
                    'observacion' => $observacion !== '' ? $observacion : 'Validado correctamente en seeder',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // ═══════════════════════════════════════════════════════════
        // 14. COMPROBANTES + PAGOS
        // ═══════════════════════════════════════════════════════════
        for ($i = 0; $i < 499; $i++) {
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
                'modalidad_pago' => $faker->randomElement(['Efectivo', 'Transferencia', 'QR']),
                'codigo_transaccion' => 'TXN-' . strtoupper($faker->bothify('??###')),
                'estado' => 'Procesado',
                'fecha' => now()->format('Y-m-d'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ═══════════════════════════════════════════════════════════
        // 15. EVALUACIONES + PROGRAMACION + NOTAS
        // ═══════════════════════════════════════════════════════════
        $evaluacionIds = [];
        $evalNombres = ['Evaluacion 1', 'Evaluacion 2', 'Evaluacion 3'];
        foreach ($evalNombres as $nombre) {
            $evalId = DB::table('evaluacion')->insertGetId([
                'nombre_eva' => $nombre,
                'puntaje_max' => 100.00,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $evaluacionIds[] = $evalId;
        }

        $programacionIds = [];
        // Asociar TODAS las materias a las 3 evaluaciones con fecha null (igual que al crear nueva gestion)
        foreach ($materiaIdsArray as $matId) {
            foreach ($evaluacionIds as $evalId) {
                $progId = DB::table('programacion_evaluacion')->insertGetId([
                    'id_evaluacion' => $evalId,
                    'id_gestionacademica' => $gestionId,
                    'id_materia' => $matId,
                    'fecha' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $programacionIds[] = $progId;
            }
        }

        // Asignar admision y notas a los primeros 499 postulantes (los que tienen pago)
        for ($i = 0; $i < 499; $i++) {
            $postId = $postulanteIds[$i];
            
            // Asumiendo que eligen la primera carrera
            $idCarrera = array_values($carreraIds)[0];

            // 1. Registrar en Admision
            DB::table('admision')->insert([
                'id_postulante' => $postId,
                'id_gestionacademica' => $gestionId,
                'id_carrera' => $idCarrera,
                'estado' => 'Registrado',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            // 2. Generar Notas
            $programaciones = DB::table('programacion_evaluacion')
                ->where('id_gestionacademica', $gestionId)
                ->get();

            foreach ($programaciones as $prog) {
                DB::table('nota')->insert([
                    'id_postulante' => $postId,
                    'id_programacion_evaluacion' => $prog->id,
                    'id_materia' => $prog->id_materia,
                    'puntaje_obtenido' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

        }

        // ═══════════════════════════════════════════════════════════
        // 16. MATERIA_REQUISITO
        // ═══════════════════════════════════════════════════════════
        $materiasDB = DB::table('materia')->get();
        foreach ($materiasDB as $mat) {
            foreach ($reqMateriaIds as $reqId) {
                $vinculo = $reqMateriaMappings[$reqId] ?? null;
                
                // Solo insertamos si el vínculo es "Todas" o coincide con el nombre de la materia
                if ($vinculo === 'Todas' || $vinculo === $mat->nombre) {
                    DB::table('materia_requisito')->updateOrInsert(
                        ['id_materia' => $mat->id, 'id_requisito' => $reqId],
                        [
                            'obligatorio' => true,
                            'estado' => 'Activo',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }

        // ═══════════════════════════════════════════════════════════
        // 17. LLENAR CHECKBOXES (Aspirante_Requisito) PARA LOS DOCENTES APROBADOS
        // ═══════════════════════════════════════════════════════════
        $postulacionesAprobadas = DB::table('postulacion_docente')->where('estado', 'Aprobado')->get();
        foreach ($postulacionesAprobadas as $post) {
            $requisitosObligatorios = DB::table('materia_requisito')->where('id_materia', $post->id_materia)->get();
            foreach ($requisitosObligatorios as $req) {
                DB::table('aspirante_requisito')->insert([
                    'id_postulacion_docente' => $post->id,
                    'id_materia_requisito' => $req->id,
                    'cumple' => true,
                    'fecha_revision' => now(),
                    'estado' => 'Cumplido',
                    'id_administrativo' => $adminPersonaId,
                    'observacion' => 'Validado automáticamente en seeder',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('✅ DemoDataSeeder completo: Roles, Carreras, Modalidades, Postulantes, Docentes, Grupos, Horarios, Requisitos (Postulante y Docente), Aspirantes.');
    }
}
