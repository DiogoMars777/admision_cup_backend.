<?php

namespace Modules\P5RecursosAcademicos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocentePortalController extends Controller
{
    public function getDashboardData(Request $request)
    {
        // El id_docente viene del usuario autenticado
        $user = $request->user();
        $idDocente = $user ? $user->id_persona : 1; // Fallback a 1 para testing si no hay auth

        // 1. Obtener la gestión activa
        $gestionActiva = DB::table('gestion_academica')->where('estado', 'Activo')->first();
        if (!$gestionActiva) {
            return response()->json(['sin_gestion' => true, 'message' => 'No hay gestión activa']);
        }

        // 2. Obtener grupos asignados al docente
        $gruposMateria = DB::table('grupo_materia')
            ->join('grupo', 'grupo.id', '=', 'grupo_materia.id_grupo')
            ->join('materia', 'materia.id', '=', 'grupo_materia.id_materia')
            ->where('grupo.id_gestionacademica', $gestionActiva->id)
            ->where('grupo_materia.id_docente', $idDocente)
            ->select(
                'grupo.id as id_grupo',
                'grupo.nombre as grupo_nombre',
                'materia.id as id_materia',
                'materia.nombre as materia_nombre',
                'grupo_materia.id as id_grupo_materia'
            )
            ->get();

        $gruposData = [];
        $totalEstudiantes = 0;
        $horarioSemanal = [];

        foreach ($gruposMateria as $gm) {
            // Contar estudiantes en este grupo
            $estudiantesCount = DB::table('postulante_grupo')->where('id_grupo', $gm->id_grupo)->count();
            $totalEstudiantes += $estudiantesCount;

            // Obtener horarios de este grupo_materia
            $horarios = DB::table('horario')
                ->join('aula', 'aula.id', '=', 'horario.id_aula')
                ->where('id_grupo_materia', $gm->id_grupo_materia)
                ->select('horario.dia', 'horario.hora_ini', 'horario.hora_fin', 'aula.aula_nro')
                ->get();

            // Resumir horario
            $dias = $horarios->pluck('dia')->unique()->implode('-');
            $hora = '';
            $aula = '';
            if ($horarios->count() > 0) {
                $hora = substr($horarios[0]->hora_ini, 0, 5) . '-' . substr($horarios[0]->hora_fin, 0, 5);
                $aula = $horarios[0]->aula_nro;
                
                // Agregar al horario semanal
                foreach ($horarios as $h) {
                    $horarioSemanal[] = [
                        'dia' => $h->dia,
                        'hora' => substr($h->hora_ini, 0, 5) . '-' . substr($h->hora_fin, 0, 5),
                        'grupo' => $gm->grupo_nombre,
                        'materia' => $gm->materia_nombre,
                        'aula' => $h->aula_nro
                    ];
                }
            }

            $gruposData[] = [
                'id' => $gm->id_grupo_materia,
                'nombre' => $gm->grupo_nombre . ' - ' . $gm->materia_nombre,
                'estudiantes' => $estudiantesCount,
                'horario' => $dias . "\n" . $hora,
                'aula' => 'Aula ' . $aula
            ];
        }

        return response()->json([
            'stats' => [
                'total' => $totalEstudiantes,
                'aprobados' => 0,
                'aprobadosPerc' => 0,
                'reprobados' => 0,
                'reprobadosPerc' => 0,
                'asistencia' => 100, // Dummy
            ],
            'grupos' => $gruposData,
            'horario_semanal' => $horarioSemanal
        ]);
    }

    public function getEstudiantesPorGrupo(Request $request, $idGrupoMateria)
    {
        // 1. Obtener a qué grupo pertenece esta materia
        $grupoMateria = DB::table('grupo_materia')->where('id', $idGrupoMateria)->first();
        if (!$grupoMateria) return response()->json([]);

        $idGrupo = $grupoMateria->id_grupo;
        $idMateria = $grupoMateria->id_materia;

        // Evaluaciones de la materia
        $evaluaciones = DB::table('programacion_evaluacion')
            ->join('evaluacion', 'evaluacion.id', '=', 'programacion_evaluacion.id_evaluacion')
            ->where('programacion_evaluacion.id_materia', $idMateria)
            ->orderBy('evaluacion.id')
            ->select('programacion_evaluacion.id', 'evaluacion.nombre_eva')
            ->get();

        $asistenciasTotales = DB::table('asistencia')->where('id_grupo_materia', $idGrupoMateria)->count();

        $estudiantes = DB::table('postulante_grupo')
            ->join('persona', 'persona.id', '=', 'postulante_grupo.id_postulante')
            ->where('postulante_grupo.id_grupo', $idGrupo)
            ->select('persona.id', 'persona.nombre', 'persona.ci')
            ->orderBy('persona.nombre', 'asc')
            ->get()
            ->map(function($e) use ($idMateria, $evaluaciones, $idGrupoMateria, $asistenciasTotales) {
                // Obtener notas reales del postulante para la materia
                $notasDB = DB::table('nota')
                    ->where('id_postulante', $e->id)
                    ->where('id_materia', $idMateria)
                    ->get()
                    ->keyBy('id_programacion_evaluacion');

                $nota1 = 0; $nota2 = 0; $nota3 = 0;

                if ($evaluaciones->count() >= 1 && isset($notasDB[$evaluaciones[0]->id])) {
                    $nota1 = $notasDB[$evaluaciones[0]->id]->puntaje_obtenido ?? 0;
                }
                if ($evaluaciones->count() >= 2 && isset($notasDB[$evaluaciones[1]->id])) {
                    $nota2 = $notasDB[$evaluaciones[1]->id]->puntaje_obtenido ?? 0;
                }
                if ($evaluaciones->count() >= 3 && isset($notasDB[$evaluaciones[2]->id])) {
                    $nota3 = $notasDB[$evaluaciones[2]->id]->puntaje_obtenido ?? 0;
                }

                // Cálculo de promedio (esto es un ejemplo simple, depende de la regla exacta)
                $promedio = ($nota1 + $nota2 + $nota3) / 3;
                $estado = $promedio >= 60 ? 'Aprobado' : 'Reprobado';
                // Si aún no han dado todos los exámenes, podría ser 'Cursando'
                if ($nota1 == 0 && $nota2 == 0 && $nota3 == 0) {
                    $estado = 'Cursando';
                }

                // Cálculo de Asistencia
                $porcentajeAsistencia = 0;
                if ($asistenciasTotales > 0) {
                    $asistenciasPostulante = DB::table('detalle_asistencia')
                        ->join('asistencia', 'asistencia.id', '=', 'detalle_asistencia.id_asistencia')
                        ->where('asistencia.id_grupo_materia', $idGrupoMateria)
                        ->where('detalle_asistencia.id_postulante', $e->id)
                        ->whereIn('detalle_asistencia.estado', ['Presente', 'Tarde'])
                        ->count();
                    $porcentajeAsistencia = round(($asistenciasPostulante / $asistenciasTotales) * 100);
                }

                return [
                    'id' => $e->id,
                    'nombre' => $e->nombre,
                    'ci' => $e->ci,
                    'nota1' => round($nota1, 1),
                    'nota2' => round($nota2, 1),
                    'nota3' => round($nota3, 1),
                    'nota' => round($promedio, 1),
                    'asistencia' => $porcentajeAsistencia,
                    'estado' => $estado
                ];
            });

        return response()->json([
            'evaluaciones' => $evaluaciones, // Para que el frontend sepa los IDs de programación
            'estudiantes' => $estudiantes
        ]);
    }

    public function guardarNotas(Request $request, $idGrupoMateria)
    {
        $grupoMateria = DB::table('grupo_materia')->where('id', $idGrupoMateria)->first();
        if (!$grupoMateria) return response()->json(['error' => 'Grupo no encontrado'], 404);

        $idMateria = $grupoMateria->id_materia;
        $estudiantesNotas = $request->input('notas', []);

        foreach ($estudiantesNotas as $est) {
            $idPostulante = $est['id'];
            foreach ($est['notas_evaluaciones'] as $idProgEval => $puntaje) {
                // Validación básica de puntaje >= 0
                $puntajeFinal = max(0, floatval($puntaje));
                
                DB::table('nota')->updateOrInsert(
                    [
                        'id_postulante' => $idPostulante, 
                        'id_programacion_evaluacion' => $idProgEval, 
                        'id_materia' => $idMateria
                    ],
                    [
                        'puntaje_obtenido' => $puntajeFinal, 
                        'updated_at' => now()
                    ]
                );
            }
        }

        return response()->json(['message' => 'Notas guardadas correctamente']);
    }

    public function getMateriasHabilitadas(Request $request)
    {
        $user = $request->user();
        $idDocente = $user ? $user->id_persona : 1;

        $materias = DB::table('docente_materia')
            ->join('materia', 'materia.id', '=', 'docente_materia.id_materia')
            ->where('docente_materia.id_docente', $idDocente)
            ->where('materia.estado', 'Activo')
            ->select('materia.id', 'materia.nombre', 'materia.descripcion')
            ->get();

        return response()->json($materias);
    }
}
