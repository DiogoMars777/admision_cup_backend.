<?php

namespace Modules\P2PostulantesYRequisitos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostulantePortalController extends Controller
{
    /**
     * Devuelve el grupo, materias, docentes y horarios del postulante autenticado.
     */
    public function getMiGrupo(Request $request)
    {
        $user = $request->user();
        $idPostulante = $user ? $user->id_persona : null;

        if (!$idPostulante) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        // Obtener grupo asignado al postulante (con datos de la gestión académica)
        $grupoPostulante = DB::table('postulante_grupo')
            ->join('grupo', 'grupo.id', '=', 'postulante_grupo.id_grupo')
            ->leftJoin('gestion_academica', 'gestion_academica.id', '=', 'grupo.id_gestionacademica')
            ->where('postulante_grupo.id_postulante', $idPostulante)
            ->select(
                'grupo.id',
                'grupo.nombre',
                'grupo.turno',
                'grupo.modalidad',
                'grupo.estado',
                'grupo.cupo_max',
                'grupo.cant_estudiante',
                'gestion_academica.id as id_gestionacademica',
                'gestion_academica.año as gestion_año',
                'gestion_academica.nombre as gestion_nombre'
            )
            ->first();

        if (!$grupoPostulante) {
            return response()->json(['grupo' => null, 'materias' => []]);
        }

        // Obtener materias del grupo con docente y horarios
        $grupoMaterias = DB::table('grupo_materia')
            ->join('materia', 'materia.id', '=', 'grupo_materia.id_materia')
            ->leftJoin('persona', 'persona.id', '=', 'grupo_materia.id_docente')
            ->where('grupo_materia.id_grupo', $grupoPostulante->id)
            ->select(
                'grupo_materia.id as id_grupo_materia',
                'materia.id as id_materia',
                'materia.nombre as materia_nombre',
                'materia.descripcion as materia_descripcion',
                'persona.nombre as docente_nombre',
                'grupo_materia.id_docente'
            )
            ->get();

        $materiasData = [];
        $todasAprobadas = true;
        $sumaPromedios = 0;
        $cantidadMaterias = count($grupoMaterias);

        foreach ($grupoMaterias as $gm) {
            // Obtener horarios de este grupo_materia
            $horarios = DB::table('horario')
                ->join('aula', 'aula.id', '=', 'horario.id_aula')
                ->where('horario.id_grupo_materia', $gm->id_grupo_materia)
                ->select(
                    'horario.dia',
                    'horario.hora_ini',
                    'horario.hora_fin',
                    'aula.aula_nro'
                )
                ->orderBy('horario.dia')
                ->orderBy('horario.hora_ini')
                ->get();

            // Resumir horario: días únicos + primera hora
            $dias = $horarios->pluck('dia')->unique()->values()->toArray();
            $horaIni = $horarios->isNotEmpty() ? substr($horarios[0]->hora_ini, 0, 5) : '';
            $horaFin = $horarios->isNotEmpty() ? substr($horarios[0]->hora_fin, 0, 5) : '';
            $aula = $horarios->isNotEmpty() ? $horarios[0]->aula_nro : 'Sin aula';

            $evaluaciones = $this->getEvaluacionesMateria($idPostulante, $gm->id_materia);
            
            // Calcular promedio de la materia (3 evaluaciones máximas)
            $sumaNotas = 0;
            $evaluacionesRealizadas = 0;
            foreach ($evaluaciones as $ev) {
                if ($ev['puntaje_obtenido'] !== null) {
                    $sumaNotas += $ev['puntaje_obtenido'];
                    $evaluacionesRealizadas++;
                }
            }
            
            // Promedio dividido entre 3 siempre según la regla de negocio
            $promedioMateria = $sumaNotas / 3;
            $estadoMateria = $promedioMateria >= 60 ? 'Aprobado' : ($evaluacionesRealizadas == 3 ? 'Reprobado' : 'Cursando');

            if ($promedioMateria < 60 && $evaluacionesRealizadas == 3) {
                $todasAprobadas = false;
            } elseif ($evaluacionesRealizadas < 3) {
                $todasAprobadas = false; // Aún no termina de dar exámenes
            }

            $sumaPromedios += $promedioMateria;

            $materiasData[] = [
                'id_materia'          => $gm->id_materia,
                'materia_nombre'      => $gm->materia_nombre,
                'materia_descripcion' => $gm->materia_descripcion,
                'docente_nombre'      => $gm->docente_nombre ?? 'Sin docente asignado',
                'aula'                => $aula,
                'dias'                => $dias,
                'hora_ini'            => $horaIni,
                'hora_fin'            => $horaFin,
                'promedio'            => round($promedioMateria, 1),
                'estado'              => $estadoMateria,
                'horarios'            => $horarios->map(function($h) {
                    return [
                        'dia'      => $h->dia,
                        'hora_ini' => substr($h->hora_ini, 0, 5),
                        'hora_fin' => substr($h->hora_fin, 0, 5),
                        'aula'     => $h->aula_nro,
                    ];
                })->values(),
                'evaluaciones'        => $evaluaciones,
            ];
        }

        $promedioFinalGeneral = $cantidadMaterias > 0 ? $sumaPromedios / $cantidadMaterias : 0;

        // Obtener datos reales de admisión desde la tabla admision
        $admisionDb = DB::table('admision')
            ->leftJoin('carrera', 'carrera.id', '=', 'admision.id_carrera')
            ->where('admision.id_postulante', $idPostulante)
            ->where('admision.id_gestionacademica', $grupoPostulante->id_gestionacademica)
            ->select('admision.estado', 'carrera.nombre as carrera_asignada')
            ->first();

        $estadoAdmision = $admisionDb ? $admisionDb->estado : 'En Proceso';
        // Solo mostrar la carrera si el estado es Aprobado (que implica que tiene cupo y se le asignó)
        $carreraAsignada = ($admisionDb && $admisionDb->estado === 'Aprobado') ? $admisionDb->carrera_asignada : null;

        return response()->json([
            'grupo'    => $grupoPostulante,
            'materias' => $materiasData,
            'admision' => [
                'promedio_final' => round($promedioFinalGeneral, 1),
                'estado' => $estadoAdmision,
                'carrera_asignada' => $carreraAsignada
            ]
        ]);
    }

    private function getEvaluacionesMateria($idPostulante, $idMateria)
    {
        return DB::table('programacion_evaluacion')
            ->join('evaluacion', 'evaluacion.id', '=', 'programacion_evaluacion.id_evaluacion')
            ->leftJoin('nota', function ($join) use ($idPostulante) {
                $join->on('nota.id_programacion_evaluacion', '=', 'programacion_evaluacion.id')
                     ->where('nota.id_postulante', '=', $idPostulante);
            })
            ->where('programacion_evaluacion.id_materia', $idMateria)
            ->select(
                'evaluacion.nombre_eva',
                'evaluacion.puntaje_max',
                'programacion_evaluacion.fecha',
                'nota.puntaje_obtenido'
            )
            ->orderBy('evaluacion.id')
            ->get()
            ->map(function($e) {
                return [
                    'nombre'           => $e->nombre_eva,
                    'puntaje_max'      => $e->puntaje_max,
                    'fecha'            => $e->fecha ? date('d/m/Y', strtotime($e->fecha)) : null,
                    'puntaje_obtenido' => $e->puntaje_obtenido,
                ];
            })->values();
    }
}
