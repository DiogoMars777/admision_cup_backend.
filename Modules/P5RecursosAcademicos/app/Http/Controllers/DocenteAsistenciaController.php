<?php

namespace Modules\P5RecursosAcademicos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class DocenteAsistenciaController extends Controller
{
    /**
     * Obtiene el historial de asistencias de un grupo.
     */
    public function getHistorial(Request $request, $idGrupoMateria)
    {
        // 1. Obtener id_grupo a partir de id_grupo_materia
        $grupoMateria = DB::table('grupo_materia')->where('id', $idGrupoMateria)->first();
        if (!$grupoMateria) {
            return response()->json(['error' => 'Grupo no encontrado'], 404);
        }

        $idGrupo = $grupoMateria->id_grupo;

        // 2. Obtener historial de asistencias de este grupo Y esta materia (id_grupo_materia)
        $asistencias = DB::table('asistencia')
            ->where('id_grupo_materia', $idGrupoMateria)
            ->orderBy('fecha', 'desc')
            ->get();

        $historial = [];
        foreach ($asistencias as $asistencia) {
            $detalles = DB::table('detalle_asistencia')
                ->where('id_asistencia', $asistencia->id)
                ->get();

            $presentes = $detalles->where('estado', 'Presente')->count();
            $tardes = $detalles->where('estado', 'Tarde')->count();
            $faltas = $detalles->where('estado', 'Falta')->count();
            $total = $detalles->count();

            $historial[] = [
                'id' => $asistencia->id,
                'fecha' => $asistencia->fecha,
                'total_estudiantes' => $total,
                'presentes' => $presentes,
                'tardes' => $tardes,
                'faltas' => $faltas,
                'estado' => 'Registrada',
            ];
        }

        return response()->json($historial);
    }

    /**
     * Crea una nueva asistencia para el grupo en una fecha específica.
     */
    public function store(Request $request, $idGrupoMateria)
    {
        $request->validate([
            'fecha' => 'required|date',
            'estudiantes' => 'required|array',
            'estudiantes.*.id_postulante' => 'required|integer',
            'estudiantes.*.estado' => 'required|in:Presente,Tarde,Falta',
        ]);

        $grupoMateria = DB::table('grupo_materia')->where('id', $idGrupoMateria)->first();
        if (!$grupoMateria) {
            return response()->json(['error' => 'Grupo no encontrado'], 404);
        }

        $idGrupo = $grupoMateria->id_grupo;
        $fecha = $request->fecha;

        // Validar que no exista asistencia para esta fecha en este grupo_materia
        $existe = DB::table('asistencia')
            ->where('id_grupo_materia', $idGrupoMateria)
            ->where('fecha', $fecha)
            ->exists();

        if ($existe) {
            return response()->json([
                'error' => 'Ya existe una asistencia registrada para este grupo en la fecha seleccionada.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Crear cabecera
            $idAsistencia = DB::table('asistencia')->insertGetId([
                'id_grupo_materia' => $idGrupoMateria,
                'fecha' => $fecha,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insertar detalles
            $detalles = [];
            foreach ($request->estudiantes as $estudiante) {
                $detalles[] = [
                    'id_asistencia' => $idAsistencia,
                    'id_postulante' => $estudiante['id_postulante'],
                    'estado' => $estudiante['estado'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('detalle_asistencia')->insert($detalles);

            DB::commit();
            return response()->json(['message' => 'Asistencia registrada correctamente', 'id' => $idAsistencia]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al registrar asistencia', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene el detalle de una asistencia específica.
     */
    public function show($id)
    {
        $asistencia = DB::table('asistencia')->where('id', $id)->first();
        if (!$asistencia) {
            return response()->json(['error' => 'Asistencia no encontrada'], 404);
        }

        $detalles = DB::table('detalle_asistencia')
            ->join('persona', 'persona.id', '=', 'detalle_asistencia.id_postulante')
            ->where('detalle_asistencia.id_asistencia', $id)
            ->select(
                'persona.id as id_postulante',
                'persona.nombre',
                'persona.ci',
                'detalle_asistencia.estado'
            )
            ->orderBy('persona.nombre', 'asc')
            ->get();

        return response()->json([
            'id' => $asistencia->id,
            'fecha' => $asistencia->fecha,
            'id_grupo_materia' => $asistencia->id_grupo_materia,
            'estudiantes' => $detalles
        ]);
    }

    /**
     * Actualiza una asistencia existente.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'estudiantes' => 'required|array',
            'estudiantes.*.id_postulante' => 'required|integer',
            'estudiantes.*.estado' => 'required|in:Presente,Tarde,Falta',
        ]);

        $asistencia = DB::table('asistencia')->where('id', $id)->first();
        if (!$asistencia) {
            return response()->json(['error' => 'Asistencia no encontrada'], 404);
        }

        DB::beginTransaction();
        try {
            // Actualizar detalles (eliminar y recrear es más fácil, o hacer update)
            DB::table('detalle_asistencia')->where('id_asistencia', $id)->delete();

            $detalles = [];
            foreach ($request->estudiantes as $estudiante) {
                $detalles[] = [
                    'id_asistencia' => $id,
                    'id_postulante' => $estudiante['id_postulante'],
                    'estado' => $estudiante['estado'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('detalle_asistencia')->insert($detalles);

            DB::commit();
            return response()->json(['message' => 'Asistencia actualizada correctamente']);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al actualizar asistencia', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Elimina una asistencia.
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            // Por FK se debería eliminar en cascada, pero lo hacemos manual por si acaso
            DB::table('detalle_asistencia')->where('id_asistencia', $id)->delete();
            DB::table('asistencia')->where('id', $id)->delete();
            
            DB::commit();
            return response()->json(['message' => 'Asistencia eliminada correctamente']);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al eliminar asistencia', 'details' => $e->getMessage()], 500);
        }
    }
}
