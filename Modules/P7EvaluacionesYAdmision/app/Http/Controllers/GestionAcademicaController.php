<?php

namespace Modules\P7EvaluacionesYAdmision\Http\Controllers;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Modules\P6PlanificacionAcademica\Models\GestionAcademica;
use Modules\P6PlanificacionAcademica\Models\GestionCup;
use Illuminate\Support\Facades\DB;

class GestionAcademicaController extends Controller
{
    public function index(Request $request)
    {
        $query = GestionAcademica::query()
            ->leftJoin('gestion_cup', 'gestion_academica.id_gestion_cup', '=', 'gestion_cup.id')
            ->select('gestion_academica.*', 'gestion_cup.nombre as cup_nombre');

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where('gestion_academica.nombre', 'ilike', "%{$search}%")
                  ->orWhere('gestion_academica.año', 'ilike', "%{$search}%");
        }

        $gestiones = $query->orderBy('gestion_academica.id', 'desc')->get();
        return response()->json($gestiones);
    }

    public function getCups()
    {
        return response()->json(GestionCup::all());
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:150',
            'id_gestion_cup' => 'required|integer',
            'fecha_ini' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_ini',
            'estado' => 'nullable|string|max:20'
        ]);

        if ($request->estado === 'Activo') {
            // Obtener gestiones previamente activas
            $gestionesViejas = GestionAcademica::where('estado', 'Activo')->pluck('id');
            
            if ($gestionesViejas->count() > 0) {
                GestionAcademica::whereIn('id', $gestionesViejas)->update(['estado' => 'Inactivo']);
                
                $rolPostulanteId = DB::table('rol')->where('nombre', 'Postulante')->value('id');
                if ($rolPostulanteId) {
                    $postulantesViejosIds = DB::table('postulante')
                        ->whereIn('id_gestionacademica', $gestionesViejas)
                        ->pluck('id_persona');
                        
                    if ($postulantesViejosIds->count() > 0) {
                        DB::table('usuario')
                            ->whereIn('id_persona', $postulantesViejosIds)
                            ->where('id_rol', $rolPostulanteId)
                            ->update(['estado' => 'Inactivo']);
                    }
                }
            }
        }

        $gestion = GestionAcademica::create([
            'nombre' => $request->nombre,
            'año' => date('Y'), // Año automático
            'id_gestion_cup' => $request->id_gestion_cup,
            'fecha_ini' => $request->fecha_ini,
            'fecha_fin' => $request->fecha_fin,
            'estado' => $request->estado ?? 'Inactivo',
        ]);

        // Crear evaluaciones por defecto si no existen
        $nombres = ['Evaluacion 1', 'Evaluacion 2', 'Evaluacion 3'];
        $evaluacionIds = [];
        foreach ($nombres as $nombre) {
            $evalId = DB::table('evaluacion')->where('nombre_eva', $nombre)->value('id');
            if (!$evalId) {
                $evalId = DB::table('evaluacion')->insertGetId([
                    'nombre_eva' => $nombre,
                    'puntaje_max' => 100,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $evaluacionIds[] = $evalId;
        }

        // Obtener todas las materias y generar la programacion_evaluacion con fecha null
        $materias = DB::table('materia')->pluck('id');
        foreach ($materias as $matId) {
            foreach ($evaluacionIds as $evalId) {
                DB::table('programacion_evaluacion')->insert([
                    'id_evaluacion' => $evalId,
                    'id_gestionacademica' => $gestion->id,
                    'id_materia' => $matId,
                    'fecha' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return response()->json(['message' => 'Gestión Académica creada exitosamente'], 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string|max:150',
            'id_gestion_cup' => 'required|integer',
            'fecha_ini' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_ini',
            'estado' => 'nullable|string|max:20'
        ]);

        $oldGestion = GestionAcademica::find($id);
        $nuevoEstado = $request->estado ?? 'Inactivo';

        $rolPostulanteId = DB::table('rol')->where('nombre', 'Postulante')->value('id');

        if ($nuevoEstado === 'Activo') {
            // Desactivar las demás gestiones
            $otrasGestiones = GestionAcademica::where('id', '!=', $id)->pluck('id');
            if ($otrasGestiones->count() > 0) {
                GestionAcademica::whereIn('id', $otrasGestiones)->update(['estado' => 'Inactivo']);
                
                // Desactivar a los postulantes de esas otras gestiones
                $postulantesOtrasIds = DB::table('postulante')
                    ->whereIn('id_gestionacademica', $otrasGestiones)
                    ->pluck('id_persona');
                
                if ($postulantesOtrasIds->count() > 0 && $rolPostulanteId) {
                    DB::table('usuario')
                        ->whereIn('id_persona', $postulantesOtrasIds)
                        ->where('id_rol', $rolPostulanteId)
                        ->update(['estado' => 'Inactivo']);
                }
            }

            // Activar a los postulantes de la gestión actual
            $postulantesIds = DB::table('postulante')->where('id_gestionacademica', $id)->pluck('id_persona');
            if ($postulantesIds->count() > 0 && $rolPostulanteId) {
                DB::table('usuario')
                    ->whereIn('id_persona', $postulantesIds)
                    ->where('id_rol', $rolPostulanteId)
                    ->update(['estado' => 'Activo']);
            }
        } else if ($nuevoEstado === 'Inactivo') {
            // Si solo se está desactivando esta gestión actual
            $postulantesIds = DB::table('postulante')->where('id_gestionacademica', $id)->pluck('id_persona');
            if ($postulantesIds->count() > 0 && $rolPostulanteId) {
                DB::table('usuario')
                    ->whereIn('id_persona', $postulantesIds)
                    ->where('id_rol', $rolPostulanteId)
                    ->update(['estado' => 'Inactivo']);
            }
        }

        GestionAcademica::where('id', $id)->update([
            'nombre' => $request->nombre,
            'id_gestion_cup' => $request->id_gestion_cup,
            'fecha_ini' => $request->fecha_ini,
            'fecha_fin' => $request->fecha_fin,
            'estado' => $nuevoEstado,
        ]);

        return response()->json(['message' => 'Gestión Académica actualizada exitosamente']);
    }

    public function destroy($id)
    {
        // Simple eliminación, se podría agregar validaciones de llaves foráneas si aplica
        GestionAcademica::where('id', $id)->delete();
        return response()->json(['message' => 'Gestión Académica eliminada exitosamente']);
    }

    public function getEvaluaciones($id)
    {
        // Los 3 nombres de evaluación globales
        $nombres = ['Evaluacion 1', 'Evaluacion 2', 'Evaluacion 3'];
        $resultado = [];

        foreach ($nombres as $nombre) {
            // Buscamos si existe alguna programacion para esta gestion con este nombre de evaluacion
            $programacion = DB::table('programacion_evaluacion')
                ->join('evaluacion', 'evaluacion.id', '=', 'programacion_evaluacion.id_evaluacion')
                ->where('programacion_evaluacion.id_gestionacademica', $id)
                ->where('evaluacion.nombre_eva', $nombre)
                ->select('programacion_evaluacion.fecha')
                ->first();

            $resultado[] = [
                'nombre_eva' => $nombre,
                'fecha' => $programacion ? $programacion->fecha : ''
            ];
        }

        return response()->json($resultado);
    }

    public function updateEvaluacion(Request $request, $id)
    {
        $request->validate([
            'nombre_eva' => 'required|string',
            'fecha' => 'required|date'
        ]);

        $nombre = $request->nombre_eva;
        $fecha = $request->fecha;

        // Buscar el ID de la evaluación global
        $evalId = DB::table('evaluacion')->where('nombre_eva', $nombre)->value('id');

        // Si no existe, crearla
        if (!$evalId) {
            $evalId = DB::table('evaluacion')->insertGetId([
                'nombre_eva' => $nombre,
                'puntaje_max' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Obtener todas las materias para asignar la programación
        $materias = DB::table('materia')->pluck('id');
        
        foreach ($materias as $matId) {
            $exists = DB::table('programacion_evaluacion')
                ->where('id_evaluacion', $evalId)
                ->where('id_gestionacademica', $id)
                ->where('id_materia', $matId)
                ->exists();

            if ($exists) {
                DB::table('programacion_evaluacion')
                    ->where('id_evaluacion', $evalId)
                    ->where('id_gestionacademica', $id)
                    ->where('id_materia', $matId)
                    ->update([
                        'fecha' => $fecha,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('programacion_evaluacion')->insert([
                    'id_evaluacion' => $evalId,
                    'id_gestionacademica' => $id,
                    'id_materia' => $matId,
                    'fecha' => $fecha,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return response()->json(['message' => 'Fecha actualizada correctamente']);
    }

    public function getGruposPostulantes($gestionId)
    {
        $grupos = DB::table('grupo')
            ->where('id_gestionacademica', $gestionId)
            ->select('id', 'nombre', 'turno', 'modalidad')
            ->orderBy('nombre')
            ->get();
            
        return response()->json($grupos);
    }

    public function getPostulantesPorGrupo($grupoId)
    {
        $postulantes = DB::table('postulante_grupo')
            ->join('postulante', 'postulante_grupo.id_postulante', '=', 'postulante.id_persona')
            ->join('persona', 'postulante.id_persona', '=', 'persona.id')
            ->where('postulante_grupo.id_grupo', $grupoId)
            ->select('persona.id', 'persona.nombre', 'persona.ci', 'persona.correo', 'persona.telefono')
            ->orderBy('persona.nombre')
            ->get();
            
        $materiasGrupo = DB::table('grupo_materia')
            ->join('materia', 'materia.id', '=', 'grupo_materia.id_materia')
            ->where('grupo_materia.id_grupo', $grupoId)
            ->select('materia.id', 'materia.nombre')
            ->get();

        foreach ($postulantes as $postulante) {
            $notasMaterias = [];
            $sumaPromedios = 0;
            $materiasTerminadas = 0;
            $aprobadoGeneral = true;
            $tieneAlgunaNota = false;

            foreach ($materiasGrupo as $materia) {
                $notas = DB::table('nota')
                    ->join('programacion_evaluacion', 'programacion_evaluacion.id', '=', 'nota.id_programacion_evaluacion')
                    ->where('nota.id_postulante', $postulante->id)
                    ->where('programacion_evaluacion.id_materia', $materia->id)
                    ->pluck('nota.puntaje_obtenido');
                
                $sumaMateria = 0;
                $notasIngresadas = 0;
                foreach ($notas as $nota) {
                    if ($nota !== null) {
                        $sumaMateria += $nota;
                        $notasIngresadas++;
                        $tieneAlgunaNota = true;
                    }
                }
                
                // Solo si tiene las 3 notas de la materia, calculamos su promedio, de lo contrario es null
                if ($notasIngresadas == 3) {
                    $promedioMateria = $sumaMateria / 3;
                    $materiasTerminadas++;
                    
                    if ($promedioMateria < 60) {
                        $aprobadoGeneral = false;
                    }
                } else {
                    $promedioMateria = null;
                    $aprobadoGeneral = false; // No puede aprobar si le faltan notas
                }
                
                $nombreLimpio = mb_strtolower(trim($materia->nombre), 'UTF-8');
                if (strpos($nombreLimpio, 'matem') !== false) $key = 'Matemática';
                else if (strpos($nombreLimpio, 'ingl') !== false) $key = 'Inglés';
                else if (strpos($nombreLimpio, 'físic') !== false || strpos($nombreLimpio, 'fisic') !== false) $key = 'Física';
                else if (strpos($nombreLimpio, 'computaci') !== false) $key = 'Computación';
                else $key = $materia->nombre;

                $notasMaterias[$key] = $promedioMateria !== null ? round($promedioMateria, 1) : null;
                if ($promedioMateria !== null) {
                    $sumaPromedios += $promedioMateria;
                }
            }

            $promedioGral = $materiasTerminadas == count($materiasGrupo) && count($materiasGrupo) > 0 
                ? $sumaPromedios / count($materiasGrupo) 
                : null;
            
            $postulante->notas = $notasMaterias;
            $postulante->promedio_final = $promedioGral !== null ? round($promedioGral, 1) : null;
            
            if ($materiasTerminadas == count($materiasGrupo) && count($materiasGrupo) > 0) {
                $postulante->estado = $aprobadoGeneral ? 'Aprobado' : 'Reprobado';
            } else {
                $postulante->estado = 'En Proceso';
            }
        }
            
        return response()->json($postulantes);
    }

    public function getResumenAdmision($id)
    {
        $gestion = GestionAcademica::findOrFail($id);
        
        $ya_asignados = DB::table('admision')
            ->where('id_gestionacademica', $id)
            ->whereIn('estado', ['Aprobado', 'Reprobado', 'Sin Cupo'])
            ->exists();
            
        $carreras = DB::table('carrera')
            ->leftJoin('cupo_carrera', function($join) use ($id) {
                $join->on('carrera.id', '=', 'cupo_carrera.id_carrera')
                     ->where('cupo_carrera.id_gestionacademica', '=', $id);
            })
            ->select('carrera.id', 'carrera.nombre', DB::raw('COALESCE(cupo_carrera.cupo_max, 50) as cupo_maximo'))
            ->get();
            
        $resultados = [];
        
        foreach ($carreras as $c) {
            $admitidos = DB::table('admision')
                ->join('persona', 'admision.id_postulante', '=', 'persona.id')
                ->where('admision.id_gestionacademica', $id)
                ->where('admision.id_carrera', $c->id)
                ->where('admision.estado', 'Aprobado')
                ->select(
                    'persona.id', 
                    'persona.nombre', 
                    'persona.ci'
                )
                ->get();
                
            foreach($admitidos as $adm) {
                $promedio = DB::table('nota')
                    ->join('programacion_evaluacion', 'nota.id_programacion_evaluacion', '=', 'programacion_evaluacion.id')
                    ->where('programacion_evaluacion.id_gestionacademica', $id)
                    ->where('nota.id_postulante', $adm->id)
                    ->avg('nota.puntaje_obtenido');
                $adm->promedio = $promedio !== null ? round($promedio, 1) : 0;
            }
            
            $admitidosArr = $admitidos->toArray();
            usort($admitidosArr, function($a, $b) {
                return $b->promedio <=> $a->promedio;
            });

            $resultados[] = [
                'id_carrera' => $c->id,
                'carrera' => $c->nombre,
                'cupo_maximo' => $c->cupo_maximo,
                'inscritos' => count($admitidosArr),
                'alumnos' => $admitidosArr
            ];
        }
        
        $reprobados = DB::table('admision')
            ->where('id_gestionacademica', $id)
            ->where('estado', 'Reprobado')
            ->count();
            
        $sin_cupo = DB::table('admision')
            ->where('id_gestionacademica', $id)
            ->where('estado', 'Sin Cupo')
            ->count();
            
        return response()->json([
            'ya_asignados' => $ya_asignados,
            'resultados' => $resultados,
            'stats' => [
                'reprobados' => $reprobados,
                'sin_cupo' => $sin_cupo
            ]
        ]);
    }
    
    public function asignarCarreras($id)
    {
        $gestion = GestionAcademica::findOrFail($id);
        
        DB::beginTransaction();
        
        try {
            $cuposCarrera = DB::table('cupo_carrera')
                ->where('id_gestionacademica', $id)
                ->get();
                
            foreach($cuposCarrera as $cc) {
                DB::table('cupo_carrera')
                    ->where('id', $cc->id)
                    ->update(['cupo_disp' => $cc->cupo_max]);
            }

            $cupos = DB::table('cupo_carrera')
                ->where('id_gestionacademica', $id)
                ->get()->keyBy('id_carrera')->toArray();

            DB::table('admision')
                ->where('id_gestionacademica', $id)
                ->update(['estado' => 'Registrado']);

            $postulantes = DB::table('postulante')
                ->where('id_gestionacademica', $id)
                ->pluck('id_persona');

            $notasPorMateria = DB::table('nota')
                ->join('programacion_evaluacion', 'nota.id_programacion_evaluacion', '=', 'programacion_evaluacion.id')
                ->where('programacion_evaluacion.id_gestionacademica', $id)
                ->select('nota.id_postulante', 'nota.id_materia', DB::raw('AVG(nota.puntaje_obtenido) as promedio_materia'))
                ->groupBy('nota.id_postulante', 'nota.id_materia')
                ->get()
                ->groupBy('id_postulante');

            $postulantesAsignables = [];
            foreach($postulantes as $pId) {
                if (!isset($notasPorMateria[$pId])) {
                    DB::table('admision')
                        ->where('id_postulante', $pId)
                        ->where('id_gestionacademica', $id)
                        ->update(['estado' => 'Reprobado', 'updated_at' => now()]);
                    continue;
                }
                
                $materias = $notasPorMateria[$pId];
                $aprobado = true;
                $suma = 0;
                
                foreach($materias as $m) {
                    $suma += $m->promedio_materia;
                    if ($m->promedio_materia < 60) {
                        $aprobado = false;
                    }
                }
                
                $promedioFinal = $suma / count($materias);
                
                if ($aprobado) {
                    $postulantesAsignables[] = [
                        'id' => $pId,
                        'promedio' => round($promedioFinal, 1)
                    ];
                } else {
                    DB::table('admision')
                        ->where('id_postulante', $pId)
                        ->where('id_gestionacademica', $id)
                        ->update(['estado' => 'Reprobado', 'updated_at' => now()]);
                }
            }

            usort($postulantesAsignables, function($a, $b) {
                return $b['promedio'] <=> $a['promedio'];
            });

            foreach($postulantesAsignables as $pa) {
                $pId = $pa['id'];
                
                $opciones = DB::table('postulante_carrera')
                    ->where('id_postulante', $pId)
                    ->orderBy('prioridad', 'asc')
                    ->pluck('id_carrera')
                    ->toArray();
                
                $asignado = null;

                foreach($opciones as $cId) {
                    if (isset($cupos[$cId]) && $cupos[$cId]->cupo_disp > 0) {
                        $asignado = $cId;
                        $cupos[$cId]->cupo_disp--;
                        break;
                    }
                }

                if (!$asignado) {
                    foreach($cupos as $cId => $c) {
                        if ($c->cupo_disp > 0) {
                            $asignado = $cId;
                            $cupos[$cId]->cupo_disp--;
                            break;
                        }
                    }
                }

                if ($asignado) {
                    DB::table('admision')
                        ->where('id_postulante', $pId)
                        ->where('id_gestionacademica', $id)
                        ->update(['estado' => 'Aprobado', 'id_carrera' => $asignado, 'updated_at' => now()]);
                } else {
                    DB::table('admision')
                        ->where('id_postulante', $pId)
                        ->where('id_gestionacademica', $id)
                        ->update(['estado' => 'Sin Cupo', 'updated_at' => now()]);
                }
            }

            foreach($cupos as $cId => $c) {
                DB::table('cupo_carrera')
                    ->where('id_carrera', $cId)
                    ->where('id_gestionacademica', $id)
                    ->update(['cupo_disp' => $c->cupo_disp]);
            }

            DB::commit();
            return response()->json(['message' => 'Admisión y asignación de carreras completada con éxito.']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al asignar carreras: ' . $e->getMessage()], 500);
        }
    }
    public function getNotasPostulante($gestionId, $postulanteId)
    {
        // Obtener las materias asignadas al estudiante (de su grupo)
        $grupo = DB::table('postulante_grupo')
            ->where('id_postulante', $postulanteId)
            ->first();

        if (!$grupo) return response()->json(['message' => 'No tiene grupo asignado'], 404);

        $materias = DB::table('grupo_materia')
            ->join('materia', 'materia.id', '=', 'grupo_materia.id_materia')
            ->where('grupo_materia.id_grupo', $grupo->id_grupo)
            ->select('materia.id', 'materia.nombre')
            ->get();

        $resultados = [];
        foreach ($materias as $m) {
            // Traer las 3 programaciones (Evaluacion 1, 2, 3)
            $programaciones = DB::table('programacion_evaluacion')
                ->join('evaluacion', 'evaluacion.id', '=', 'programacion_evaluacion.id_evaluacion')
                ->where('programacion_evaluacion.id_gestionacademica', $gestionId)
                ->where('programacion_evaluacion.id_materia', $m->id)
                ->orderBy('evaluacion.nombre_eva')
                ->select('programacion_evaluacion.id', 'evaluacion.nombre_eva')
                ->get();

            $notasDetalle = [];
            foreach ($programaciones as $prog) {
                $nota = DB::table('nota')
                    ->where('id_postulante', $postulanteId)
                    ->where('id_programacion_evaluacion', $prog->id)
                    ->value('puntaje_obtenido');
                
                $notasDetalle[] = [
                    'id_programacion' => $prog->id,
                    'evaluacion' => $prog->nombre_eva,
                    'nota' => $nota // null o el numero
                ];
            }

            $resultados[] = [
                'id_materia' => $m->id,
                'nombre' => $m->nombre,
                'evaluaciones' => $notasDetalle
            ];
        }

        return response()->json($resultados);
    }

    public function updateNotasPostulante(Request $request, $gestionId, $postulanteId)
    {
        $notas = $request->input('notas', []);
        
        DB::beginTransaction();
        try {
            foreach ($notas as $n) {
                // Convertir nota vacia a null
                $valorNota = ($n['nota'] === '' || $n['nota'] === null) ? null : floatval($n['nota']);
                
                DB::table('nota')
                    ->where('id_postulante', $postulanteId)
                    ->where('id_programacion_evaluacion', $n['id_programacion'])
                    ->update(['puntaje_obtenido' => $valorNota, 'updated_at' => now()]);
            }
            DB::commit();
            return response()->json(['message' => 'Notas actualizadas exitosamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar notas: ' . $e->getMessage()], 500);
        }
    }
}
