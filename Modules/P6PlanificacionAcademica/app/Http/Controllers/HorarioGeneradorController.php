<?php

namespace Modules\P6PlanificacionAcademica\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HorarioGeneradorController extends Controller
{
    public function getResumen($id)
    {
        $grupos = DB::table('grupo')->where('id_gestionacademica', $id)->count();
        $docentes = DB::table('docente')->count();
        $aulas = DB::table('aula')->count();
        $materias = DB::table('materia')
            ->whereIn('nombre', ['Matemáticas', 'Física', 'Computación', 'Inglés'])
            ->count();

        $ya_generados = DB::table('horario')
            ->join('grupo_materia', 'horario.id_grupo_materia', '=', 'grupo_materia.id')
            ->join('grupo', 'grupo_materia.id_grupo', '=', 'grupo.id')
            ->where('grupo.id_gestionacademica', $id)
            ->exists();

        $horarios = [];
        if ($ya_generados) {
            $horarios = DB::table('horario')
                ->join('grupo_materia', 'horario.id_grupo_materia', '=', 'grupo_materia.id')
                ->join('grupo', 'grupo_materia.id_grupo', '=', 'grupo.id')
                ->join('materia', 'grupo_materia.id_materia', '=', 'materia.id')
                ->leftJoin('persona as docente', 'grupo_materia.id_docente', '=', 'docente.id')
                ->join('aula', 'horario.id_aula', '=', 'aula.id')
                ->where('grupo.id_gestionacademica', $id)
                ->select(
                    'grupo.id as id_grupo',
                    'grupo.nombre as grupo_nombre',
                    'grupo.turno',
                    'grupo.modalidad',
                    'materia.id as id_materia',
                    'materia.nombre as materia_nombre',
                    'docente.id as id_docente',
                    'docente.nombre as docente_nombre',
                    'aula.id as id_aula',
                    'aula.aula_nro as aula_nro',
                    'horario.dia',
                    'horario.hora_ini',
                    'horario.hora_fin'
                )
                ->get()
                ->map(function($h) {
                    // Normalize to match generation output
                    if (!$h->docente_nombre) {
                        $h->docente_nombre = 'Sin docente asignado';
                    }
                    return $h;
                });
        }

        return response()->json([
            'total_grupos' => $grupos,
            'docentes_disponibles' => $docentes,
            'aulas_disponibles' => $aulas,
            'total_materias' => $materias,
            'ya_generados' => $ya_generados,
            'horarios_guardados' => $horarios
        ]);
    }

    public function simular($id)
    {
        $resultado = $this->generarAlgoritmo($id, true);
        return response()->json($resultado);
    }

    public function generar($id)
    {
        $resultado = $this->generarAlgoritmo($id, false);
        return response()->json($resultado);
    }

    private function generarAlgoritmo($id_gestion, $es_simulacion)
    {
        $grupos = DB::table('grupo')->where('id_gestionacademica', $id_gestion)->get();
        if ($grupos->count() === 0) {
            return ['error' => true, 'message' => 'No existen grupos habilitados para esta gestión.'];
        }

        $materiasDb = DB::table('materia')->whereIn('nombre', ['Matemática', 'Física', 'Computación', 'Inglés'])->get()->keyBy('nombre');
        if ($materiasDb->count() < 4) {
            return ['error' => true, 'message' => 'Faltan materias base en la base de datos (Matemática, Física, Computación, Inglés).'];
        }

        $docentes = DB::table('docente')
            ->join('persona', 'docente.id_persona', '=', 'persona.id')
            ->select('docente.id_persona as id', 'persona.nombre')
            ->get();
        
        $aulas = DB::table('aula')->get();

        if ($docentes->count() < 4) {
            return ['error' => true, 'message' => 'No hay suficientes docentes disponibles para generar el horario.'];
        }
        if ($aulas->count() < 4) {
            return ['error' => true, 'message' => 'No hay suficientes aulas disponibles.'];
        }

        $matrizBase = [
            ['Matemática', 'Inglés', 'Computación', 'Física'],
            ['Inglés', 'Matemática', 'Física', 'Computación'],
            ['Computación', 'Física', 'Inglés', 'Matemática'],
            ['Física', 'Computación', 'Matemática', 'Inglés']
        ];

        $horariosGenerados = [];
        $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];


        if (!$es_simulacion) {
            DB::beginTransaction();
            // Limpiar horarios previos de estos grupos
            $gruposIds = $grupos->pluck('id')->toArray();
            $gmIds = DB::table('grupo_materia')->whereIn('id_grupo', $gruposIds)->pluck('id')->toArray();
            if (!empty($gmIds)) {
                DB::table('horario')->whereIn('id_grupo_materia', $gmIds)->delete();
            }
        }

        foreach ($grupos as $index => $grupo) {
            $patronIdx = $index % 4;
            $patron = $matrizBase[$patronIdx];

            $bloques = $this->getBloquesTurno($grupo->turno);

            foreach ($dias as $dia) {
                // 4 bloques de materias
                for ($b = 0; $b < 4; $b++) {
                    $materiaNombre = $patron[$b];
                    $materiaId = $materiasDb[$materiaNombre]->id;
                    // Rotar aulas por grupo
                    $aula = $aulas[($index + $b) % $aulas->count()];

                    if (!$es_simulacion) {
                        $grupoMateriaId = DB::table('grupo_materia')->where('id_grupo', $grupo->id)->where('id_materia', $materiaId)->value('id');
                        if (!$grupoMateriaId) {
                            $grupoMateriaId = DB::table('grupo_materia')->insertGetId([
                                'id_grupo' => $grupo->id,
                                'id_materia' => $materiaId,
                                'id_docente' => null, // Se asigna luego en el módulo Docentes
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        $horarioInsert = [
                            'id_grupo_materia' => $grupoMateriaId,
                            'id_aula' => $aula->id,
                            'dia' => $dia,
                            'hora_ini' => $bloques[$b][0],
                            'hora_fin' => $bloques[$b][1],
                            'modalidad' => $grupo->modalidad,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        DB::table('horario')->insert($horarioInsert);
                    }

                    $horarioItem = [
                        'id_grupo' => $grupo->id,
                        'id_docente' => null,
                        'id_materia' => $materiaId,
                        'id_aula' => $aula->id,
                        'dia' => $dia,
                        'hora_ini' => $bloques[$b][0],
                        'hora_fin' => $bloques[$b][1],
                        'modalidad' => $grupo->modalidad,
                        'materia_nombre' => $materiaNombre,
                        'docente_nombre' => 'Sin docente asignado',
                        'aula_nro' => $aula->aula_nro,
                        'grupo_nombre' => $grupo->nombre,
                        'turno' => $grupo->turno,
                    ];
                    
                    $horariosGenerados[] = $horarioItem;
                }
            }
        }

        if (!$es_simulacion) {
            DB::commit();
        }

        return [
            'error' => false,
            'horarios' => $horariosGenerados
        ];
    }

    private function getBloquesTurno($turno)
    {
        if ($turno === 'Mañana') {
            return [
                ['08:00', '09:00'],
                ['09:00', '10:00'],
                ['10:00', '11:00'],
                ['11:00', '12:00']
            ];
        } elseif ($turno === 'Tarde') {
            return [
                ['13:00', '14:00'],
                ['14:00', '15:00'],
                ['15:00', '16:00'],
                ['16:00', '17:00']
            ];
        } else {
            // Noche
            return [
                ['18:00', '19:00'],
                ['19:00', '20:00'],
                ['20:00', '21:00'],
                ['21:00', '22:00']
            ];
        }
    }
}
