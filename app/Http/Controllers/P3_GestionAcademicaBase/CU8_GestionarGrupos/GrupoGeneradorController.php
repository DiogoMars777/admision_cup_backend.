<?php

namespace App\Http\Controllers\P3_GestionAcademicaBase\CU8_GestionarGrupos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GrupoGeneradorController extends Controller
{
    public function getResumen($id)
    {
        $gestion = DB::table('gestion_academica')->where('id', $id)->first();
        if (!$gestion) {
            return response()->json(['message' => 'Gestión no encontrada'], 404);
        }

        $postulantes = DB::table('postulante')
            ->join('persona', 'postulante.id_persona', '=', 'persona.id')
            ->join('usuario', 'usuario.id_persona', '=', 'persona.id')
            ->where('postulante.id_gestionacademica', $id)
            ->where('usuario.estado', 'Activo')
            ->select(
                'persona.id', 'persona.nombre', 
                'postulante.modalidad_preferida', 'postulante.turno_preferido'
            )
            ->get();

        $total_inscritos = $postulantes->count();
        $capacidad = 70;
        $cantidad_grupos = ceil($total_inscritos / $capacidad);

        // Modalidades (Presencial, Virtual, Sin modalidad)
        $mod_presencial = $postulantes->where('modalidad_preferida', 'Presencial')->count();
        $mod_virtual = $postulantes->where('modalidad_preferida', 'Virtual')->count();
        $mod_sin = max(0, $total_inscritos - $mod_presencial - $mod_virtual);

        // Turnos
        $turno_manana = $postulantes->where('turno_preferido', 'Mañana')->count();
        $turno_tarde = $postulantes->where('turno_preferido', 'Tarde')->count();
        $turno_noche = $postulantes->where('turno_preferido', 'Noche')->count();
        $turno_sin = max(0, $total_inscritos - $turno_manana - $turno_tarde - $turno_noche);

        // Check if groups exist
        $grupos_existentes = DB::table('grupo')->where('id_gestionacademica', $id)->get();
        
        $total_asignados = 0;
        if ($grupos_existentes->count() > 0) {
            $grupos_ids = $grupos_existentes->pluck('id')->toArray();
            $total_asignados = DB::table('postulante_grupo')
                ->join('postulante', 'postulante.id_persona', '=', 'postulante_grupo.id_postulante')
                ->where('postulante.id_gestionacademica', $id)
                ->whereIn('postulante_grupo.id_grupo', $grupos_ids)
                ->count();
        }

        $pendientes = max(0, $total_inscritos - $total_asignados);

        return response()->json([
            'total_inscritos' => $total_inscritos,
            'cantidad_grupos' => $cantidad_grupos,
            'capacidad' => $capacidad,
            'total_asignados' => $total_asignados,
            'pendientes_asignacion' => $pendientes,
            'modalidades' => [
                'Presencial' => $mod_presencial,
                'Virtual' => $mod_virtual,
                'Sin modalidad' => $mod_sin
            ],
            'turnos' => [
                'Mañana' => $turno_manana,
                'Tarde' => $turno_tarde,
                'Noche' => $turno_noche,
                'Sin preferencia' => $turno_sin
            ],
            'grupos' => $grupos_existentes,
            'ya_generados' => $grupos_existentes->count() > 0
        ]);
    }

    private function calcularAsignacion($id)
    {
        $gestion = DB::table('gestion_academica')->where('id', $id)->first();
        $postulantes = DB::table('postulante')
            ->join('persona', 'postulante.id_persona', '=', 'persona.id')
            ->join('usuario', 'usuario.id_persona', '=', 'persona.id')
            ->where('postulante.id_gestionacademica', $id)
            ->where('usuario.estado', 'Activo')
            ->select(
                'persona.id', 'persona.nombre', 
                'postulante.modalidad_preferida', 'postulante.turno_preferido'
            )
            ->orderBy('persona.nombre')
            ->get();

        $total_inscritos = $postulantes->count();
        $capacidad = 70;
        $cantidad_grupos = ceil($total_inscritos / $capacidad);

        $grupos = [];
        $pendientes = [];

        if ($total_inscritos === 0) {
            return ['grupos' => $grupos, 'pendientes' => $pendientes];
        }

        // Determinar preferencias más populares
        $preferencias = [];
        foreach ($postulantes as $p) {
            $mod = $p->modalidad_preferida ?: 'Presencial'; // Default
            $tur = $p->turno_preferido ?: 'Mañana'; // Default
            $key = $mod . '|' . $tur;
            if (!isset($preferencias[$key])) {
                $preferencias[$key] = 0;
            }
            $preferencias[$key]++;
        }

        arsort($preferencias);
        $combinaciones = array_keys($preferencias);

        $letras = range('A', 'Z');
        $ano = $gestion->año ?? date('Y');

        for ($i = 0; $i < $cantidad_grupos; $i++) {
            $comb_key = $combinaciones[$i % count($combinaciones)];
            [$mod, $tur] = explode('|', $comb_key);

            $grupos[] = [
                'temp_id' => $i + 1,
                'nombre' => 'Grupo ' . $letras[$i % 26] . ' - ' . $ano,
                'modalidad' => $mod,
                'turno' => $tur,
                'cupo_max' => $capacidad,
                'cupo_actual' => 0,
                'porcentaje' => 0,
                'estado' => 'Activo',
                'inscritos' => []
            ];
        }

        // Asignar postulantes
        foreach ($postulantes as $p) {
            $asignado = false;

            // Intentar match perfecto (Modalidad y Turno)
            foreach ($grupos as &$g) {
                if ($g['cupo_actual'] < $g['cupo_max']) {
                    $matchMod = (!$p->modalidad_preferida) || ($g['modalidad'] === $p->modalidad_preferida);
                    $matchTur = (!$p->turno_preferido) || ($g['turno'] === $p->turno_preferido);

                    if ($matchMod && $matchTur) {
                        $g['inscritos'][] = $p->id;
                        $g['cupo_actual']++;
                        $asignado = true;
                        break;
                    }
                }
            }

            // Si no, intentar match de Modalidad O Turno
            if (!$asignado) {
                foreach ($grupos as &$g) {
                    if ($g['cupo_actual'] < $g['cupo_max']) {
                        $matchMod = (!$p->modalidad_preferida) || ($g['modalidad'] === $p->modalidad_preferida);
                        $matchTur = (!$p->turno_preferido) || ($g['turno'] === $p->turno_preferido);

                        if ($matchMod || $matchTur) {
                            $g['inscritos'][] = $p->id;
                            $g['cupo_actual']++;
                            $asignado = true;
                            break;
                        }
                    }
                }
            }

            // Si no, asignar al primer grupo con espacio
            if (!$asignado) {
                foreach ($grupos as &$g) {
                    if ($g['cupo_actual'] < $g['cupo_max']) {
                        $g['inscritos'][] = $p->id;
                        $g['cupo_actual']++;
                        $asignado = true;
                        break;
                    }
                }
            }

            // Si definitivamente no hay espacio (no debería pasar por el ceil)
            if (!$asignado) {
                $pendientes[] = [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'modalidad_registrada' => $p->modalidad_preferida ?: 'Sin modalidad',
                    'turno_preferido' => $p->turno_preferido ?: 'Sin preferencia',
                    'motivo' => 'No hay cupo disponible en grupos compatibles',
                    'accion_sugerida' => 'Apersonarse a Dirección Académica'
                ];
            }
        }

        // Calcular porcentajes
        foreach ($grupos as &$g) {
            $g['porcentaje'] = round(($g['cupo_actual'] / $g['cupo_max']) * 100);
        }

        return ['grupos' => $grupos, 'pendientes' => $pendientes];
    }

    public function simular($id)
    {
        $grupos_existentes = DB::table('grupo')->where('id_gestionacademica', $id)->count();
        if ($grupos_existentes > 0) {
            return response()->json(['message' => 'Los grupos ya fueron generados para esta gestión académica.'], 400);
        }

        $resultado = $this->calcularAsignacion($id);
        
        // Remove 'inscritos' details for simulation response to keep it light
        $grupos_simulados = array_map(function($g) {
            unset($g['inscritos']);
            return $g;
        }, $resultado['grupos']);

        return response()->json([
            'grupos' => $grupos_simulados,
            'pendientes' => $resultado['pendientes']
        ]);
    }

    public function generar(Request $request, $id)
    {
        $grupos_existentes = DB::table('grupo')->where('id_gestionacademica', $id)->count();
        if ($grupos_existentes > 0) {
            return response()->json(['message' => 'Los grupos ya fueron generados para esta gestión académica.'], 400);
        }

        $resultado = $this->calcularAsignacion($id);

        if (count($resultado['grupos']) === 0) {
            return response()->json(['message' => 'No existen postulantes inscritos para esta gestión académica.'], 400);
        }

        DB::beginTransaction();
        try {
            foreach ($resultado['grupos'] as $g) {
                $grupoId = DB::table('grupo')->insertGetId([
                    'id_gestionacademica' => $id,
                    'nombre' => $g['nombre'],
                    'cupo_max' => $g['cupo_max'],
                    'cant_estudiante' => $g['cupo_actual'],
                    'modalidad' => $g['modalidad'],
                    'turno' => $g['turno'],
                    'estado' => $g['estado'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $insertData = [];
                foreach ($g['inscritos'] as $postulanteId) {
                    $insertData[] = [
                        'id_postulante' => $postulanteId,
                        'id_grupo' => $grupoId,
                        'fecha_asignacion' => now()->toDateString(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (count($insertData) > 0) {
                    DB::table('postulante_grupo')->insert($insertData);
                }
            }
            DB::commit();
            return response()->json(['message' => 'Grupos habilitados correctamente.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al generar grupos: ' . $e->getMessage()], 500);
        }
    }
}
