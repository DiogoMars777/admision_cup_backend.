<?php

namespace App\Http\Controllers\P3_GestionAcademicaBase\Docentes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocenteAsignadorController extends Controller
{
    public function getResumen(Request $request, $gestionId)
    {
        // 1. Grupos habilitados (los que tienen grupo_materia con horario asignado)
        $gruposHabilitadosIds = DB::table('grupo')
            ->join('grupo_materia', 'grupo.id', '=', 'grupo_materia.id_grupo')
            ->join('horario', 'grupo_materia.id', '=', 'horario.id_grupo_materia')
            ->where('grupo.id_gestionacademica', $gestionId)
            ->pluck('grupo.id')
            ->unique();
            
        $gruposHabilitadosCount = $gruposHabilitadosIds->count();

        // 2. Materias programadas (aquellas en grupo_materia con horario para los grupos de la gestion)
        $materiasProgramadasCount = DB::table('grupo_materia')
            ->join('grupo', 'grupo.id', '=', 'grupo_materia.id_grupo')
            ->join('horario', 'grupo_materia.id', '=', 'horario.id_grupo_materia')
            ->where('grupo.id_gestionacademica', $gestionId)
            ->distinct()
            ->count('grupo_materia.id');

        // 3. Asignaciones activas (grupo_materia con id_docente NOT NULL)
        $asignacionesActivasCount = DB::table('grupo_materia')
            ->join('grupo', 'grupo.id', '=', 'grupo_materia.id_grupo')
            ->where('grupo.id_gestionacademica', $gestionId)
            ->whereNotNull('grupo_materia.id_docente')
            ->count();

        // 4. Docentes asignados (cantidad única de docentes)
        $docentesAsignadosCount = DB::table('grupo_materia')
            ->join('grupo', 'grupo.id', '=', 'grupo_materia.id_grupo')
            ->where('grupo.id_gestionacademica', $gestionId)
            ->whereNotNull('grupo_materia.id_docente')
            ->distinct()
            ->count('grupo_materia.id_docente');

        // 5. Total docentes disponibles (docentes habilitados para al menos una materia de los grupos de la gestion)
        $materiasIds = DB::table('grupo_materia')
            ->join('grupo', 'grupo.id', '=', 'grupo_materia.id_grupo')
            ->where('grupo.id_gestionacademica', $gestionId)
            ->pluck('id_materia')
            ->unique();

        $totalDocentesDisponibles = DB::table('docente_materia')
            ->whereIn('id_materia', $materiasIds)
            ->distinct()
            ->count('id_docente');

        // Obtener Asignaciones Actuales para la tabla
        $asignaciones = DB::table('grupo_materia')
            ->join('grupo', 'grupo.id', '=', 'grupo_materia.id_grupo')
            ->join('materia', 'materia.id', '=', 'grupo_materia.id_materia')
            ->leftJoin('persona as docente', 'docente.id', '=', 'grupo_materia.id_docente')
            ->join('horario', 'grupo_materia.id', '=', 'horario.id_grupo_materia')
            ->join('aula', 'aula.id', '=', 'horario.id_aula')
            ->where('grupo.id_gestionacademica', $gestionId)
            ->whereNotNull('grupo_materia.id_docente')
            ->select(
                'grupo_materia.id as id_grupo_materia',
                'grupo.nombre as grupo_nombre',
                'materia.nombre as materia_nombre',
                'docente.nombre as docente_nombre',
                'aula.aula_nro as aula_nombre',
                'horario.dia',
                'horario.hora_ini',
                'horario.hora_fin',
                'horario.modalidad'
            )
            ->get()
            ->groupBy('id_grupo_materia')
            ->map(function($grupoHorarios) {
                $first = $grupoHorarios->first();
                $dias = $grupoHorarios->pluck('dia')->unique()->implode(', ');
                
                return [
                    'id_grupo_materia' => $first->id_grupo_materia,
                    'grupo_nombre' => $first->grupo_nombre,
                    'materia_nombre' => $first->materia_nombre,
                    'docente_nombre' => $first->docente_nombre,
                    'aula_nombre' => $first->aula_nombre,
                    'dia' => $dias,
                    'hora' => substr($first->hora_ini, 0, 5) . ' - ' . substr($first->hora_fin, 0, 5),
                    'modalidad' => $first->modalidad,
                    'estado' => 'Activo'
                ];
            })->values();

        return response()->json([
            'stats' => [
                'total_disponibles' => $totalDocentesDisponibles,
                'docentes_asignados' => $docentesAsignadosCount,
                'grupos_habilitados' => $gruposHabilitadosCount,
                'materias_programadas' => $materiasProgramadasCount,
                'asignaciones_activas' => $asignacionesActivasCount,
            ],
            'asignaciones' => $asignaciones
        ]);
    }

    public function getGruposProgramados($gestionId)
    {
        $grupos = DB::table('grupo')
            ->where('id_gestionacademica', $gestionId)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('grupo_materia')
                      ->join('horario', 'grupo_materia.id', '=', 'horario.id_grupo_materia')
                      ->whereColumn('grupo_materia.id_grupo', 'grupo.id');
            })
            ->select('id', 'nombre', 'turno', 'modalidad')
            ->get();
            
        return response()->json($grupos);
    }

    public function getMateriasDeGrupo($grupoId)
    {
        $materias = DB::table('grupo_materia')
            ->join('materia', 'materia.id', '=', 'grupo_materia.id_materia')
            ->join('horario', 'grupo_materia.id', '=', 'horario.id_grupo_materia')
            ->join('aula', 'aula.id', '=', 'horario.id_aula')
            ->where('grupo_materia.id_grupo', $grupoId)
            ->select(
                'grupo_materia.id as id_grupo_materia',
                'materia.id as id_materia',
                'materia.nombre',
                'grupo_materia.id_docente',
                'aula.aula_nro as nro_aula',
                'horario.dia',
                'horario.hora_ini',
                'horario.hora_fin',
                'horario.modalidad'
            )
            ->get()
            ->groupBy('id_grupo_materia')
            ->map(function($grupoHorarios) {
                $first = $grupoHorarios->first();
                // Reunir los días si varían
                $dias = $grupoHorarios->pluck('dia')->unique()->implode(', ');
                
                return [
                    'id_grupo_materia' => $first->id_grupo_materia,
                    'id_materia' => $first->id_materia,
                    'nombre' => $first->nombre,
                    'id_docente' => $first->id_docente,
                    'nro_aula' => $first->nro_aula,
                    'dia' => $dias, // "Lunes, Martes..."
                    'hora' => substr($first->hora_ini, 0, 5) . ' - ' . substr($first->hora_fin, 0, 5),
                    'modalidad' => $first->modalidad
                ];
            })->values();
            
        return response()->json($materias);
    }

    public function getDocentesHabilitados($materiaId)
    {
        $docentes = DB::table('docente_materia')
            ->join('persona', 'persona.id', '=', 'docente_materia.id_docente')
            ->where('docente_materia.id_materia', $materiaId)
            ->select('persona.id', 'persona.nombre', 'persona.ci')
            ->get();
            
        return response()->json($docentes);
    }

    public function asignarDocente(Request $request, $gestionId, $grupoMateriaId)
    {
        $request->validate([
            'id_docente' => 'required|exists:persona,id'
        ]);

        $idDocente = $request->id_docente;

        // Validar si la materia ya tiene docente
        $gm = DB::table('grupo_materia')->where('id', $grupoMateriaId)->first();
        if (!$gm) return response()->json(['message' => 'Grupo Materia no encontrado.'], 404);
        
        // Obtenemos su horario
        $horarioDestino = DB::table('horario')->where('id_grupo_materia', $grupoMateriaId)->first();
        if (!$horarioDestino) {
            return response()->json(['message' => 'La materia seleccionada aún no tiene horario o aula configurada.'], 400);
        }

        if ($gm->id_docente == $idDocente) {
            return response()->json(['message' => 'La asignación ya existe.'], 400);
        }

        // Validar máximo 4 materias para este docente en la misma gestión
        $materiasAsignadas = DB::table('grupo_materia')
            ->join('grupo', 'grupo.id', '=', 'grupo_materia.id_grupo')
            ->where('grupo.id_gestionacademica', $gestionId)
            ->where('grupo_materia.id_docente', $idDocente)
            ->where('grupo_materia.id', '!=', $grupoMateriaId) // No contar si está editando la misma
            ->count();
            
        if ($materiasAsignadas >= 4) {
            return response()->json(['message' => 'El docente ya alcanzó el máximo de 4 materias asignadas.'], 400);
        }

        // Validar cruce de horarios
        $cruces = DB::table('grupo_materia')
            ->join('grupo', 'grupo.id', '=', 'grupo_materia.id_grupo')
            ->join('horario', 'grupo_materia.id', '=', 'horario.id_grupo_materia')
            ->where('grupo.id_gestionacademica', $gestionId)
            ->where('grupo_materia.id_docente', $idDocente)
            ->where('grupo_materia.id', '!=', $grupoMateriaId)
            ->where('horario.dia', $horarioDestino->dia)
            ->where(function ($query) use ($horarioDestino) {
                // Verificar solapamiento: (A.start < B.end AND A.end > B.start)
                $query->where('horario.hora_ini', '<', $horarioDestino->hora_fin)
                      ->where('horario.hora_fin', '>', $horarioDestino->hora_ini);
            })
            ->count();

        if ($cruces > 0) {
            return response()->json(['message' => 'Existe cruce de horario para este docente.'], 400);
        }

        // Asignar docente
        DB::table('grupo_materia')
            ->where('id', $grupoMateriaId)
            ->update([
                'id_docente' => $idDocente,
                'updated_at' => now()
            ]);

        return response()->json(['message' => 'Docente asignado correctamente.']);
    }

    public function quitarDocente($gestionId, $grupoMateriaId)
    {
        DB::table('grupo_materia')
            ->where('id', $grupoMateriaId)
            ->update([
                'id_docente' => null,
                'updated_at' => now()
            ]);

        return response()->json(['message' => 'Docente quitado correctamente.']);
    }

    public function asignacionAutomatica($gestionId)
    {
        // 1. Obtener todas las materias_grupo de la gestión que NO tienen docente asignado
        $gruposMateriaSinAsignar = DB::table('grupo_materia')
            ->join('grupo', 'grupo.id', '=', 'grupo_materia.id_grupo')
            ->join('horario', 'grupo_materia.id', '=', 'horario.id_grupo_materia')
            ->where('grupo.id_gestionacademica', $gestionId)
            ->whereNull('grupo_materia.id_docente')
            ->select(
                'grupo_materia.id as id_grupo_materia',
                'grupo_materia.id_materia',
                'horario.dia',
                'horario.hora_ini',
                'horario.hora_fin'
            )
            ->get()
            ->groupBy('id_grupo_materia');

        $asignados = 0;

        foreach ($gruposMateriaSinAsignar as $idGrupoMateria => $horarios) {
            $idMateria = $horarios->first()->id_materia;

            $docentesHabilitados = DB::table('docente_materia')
                ->join('persona as docente', 'docente.id', '=', 'docente_materia.id_docente')
                ->where('docente_materia.id_materia', $idMateria)
                ->pluck('docente.id');

            $docentesHabilitados = $docentesHabilitados->shuffle();

            foreach ($docentesHabilitados as $idDocente) {
                $materiasAsignadas = DB::table('grupo_materia')
                    ->join('grupo', 'grupo.id', '=', 'grupo_materia.id_grupo')
                    ->where('grupo.id_gestionacademica', $gestionId)
                    ->where('grupo_materia.id_docente', $idDocente)
                    ->count();

                if ($materiasAsignadas >= 4) continue;

                $tieneCruce = false;
                foreach ($horarios as $horarioDestino) {
                    $cruces = DB::table('grupo_materia')
                        ->join('grupo', 'grupo.id', '=', 'grupo_materia.id_grupo')
                        ->join('horario', 'grupo_materia.id', '=', 'horario.id_grupo_materia')
                        ->where('grupo.id_gestionacademica', $gestionId)
                        ->where('grupo_materia.id_docente', $idDocente)
                        ->where('horario.dia', $horarioDestino->dia)
                        ->where(function ($query) use ($horarioDestino) {
                            $query->where('horario.hora_ini', '<', $horarioDestino->hora_fin)
                                  ->where('horario.hora_fin', '>', $horarioDestino->hora_ini);
                        })
                        ->count();

                    if ($cruces > 0) {
                        $tieneCruce = true;
                        break;
                    }
                }

                if ($tieneCruce) continue;

                DB::table('grupo_materia')
                    ->where('id', $idGrupoMateria)
                    ->update([
                        'id_docente' => $idDocente,
                        'updated_at' => now()
                    ]);

                $asignados++;
                break;
            }
        }

        return response()->json([
            'message' => "Asignación automática completada. Se asignaron {$asignados} docentes a materias que estaban pendientes."
        ]);
    }
}
