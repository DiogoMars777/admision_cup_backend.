<?php

namespace Modules\P2PostulantesYRequisitos\Http\Controllers;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequisitoController extends Controller
{
    // --- 1. GESTIÓN DEL CATÁLOGO DE REQUISITOS (BASE) ---
    
    public function getCatalogo()
    {
        return response()->json(\Modules\P2PostulantesYRequisitos\Models\Requisito::orderBy('id', 'desc')->get());
    }

    public function storeCatalogo(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:255',
            'tipo_requisito' => 'required|in:Postulante,Materia',
            'estado' => 'required|in:Activo,Inactivo'
        ]);

        $existe = \Modules\P2PostulantesYRequisitos\Models\Requisito::query()
            ->where('nombre', $request->nombre)
            ->where('tipo_requisito', $request->tipo_requisito)
            ->exists();

        if ($existe) {
            return response()->json(['message' => 'Ya existe un requisito con este nombre y tipo.'], 422);
        }

        $user = $request->user();
        \Modules\P2PostulantesYRequisitos\Models\Requisito::insert([
            'id_abministrador' => $user ? ($user->id_persona ?? 1) : 1,
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'tipo_requisito' => $request->tipo_requisito,
            'estado' => $request->estado,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json(['message' => 'Requisito base creado en el catálogo.']);
    }

    public function updateCatalogo(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:255',
            'tipo_requisito' => 'required|in:Postulante,Materia',
            'estado' => 'required|in:Activo,Inactivo'
        ]);

        \Modules\P2PostulantesYRequisitos\Models\Requisito::where('id', $id)->update([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'tipo_requisito' => $request->tipo_requisito,
            'estado' => $request->estado,
            'updated_at' => now()
        ]);

        return response()->json(['message' => 'Requisito base actualizado.']);
    }

    public function deleteCatalogo($id)
    {
        \Modules\P2PostulantesYRequisitos\Models\Requisito::where('id', $id)->delete();
        return response()->json(['message' => 'Requisito base eliminado.']);
    }

    // --- ASIGNACIÓN DE REQUISITOS POR MATERIA ---
    public function getMateriaRequisitos($materiaId)
    {
        // Devuelve todos los requisitos tipo Materia, y si están asignados a la materia, incluye esa información
        $requisitosMateria = \Modules\P2PostulantesYRequisitos\Models\Requisito::query()
            ->where('tipo_requisito', 'Materia')
            ->leftJoin('materia_requisito', function ($join) use ($materiaId) {
                $join->on('requisito.id', '=', 'materia_requisito.id_requisito')
                     ->where('materia_requisito.id_materia', '=', $materiaId);
            })
            ->where(function ($query) {
                $query->where('requisito.estado', 'Activo')
                      ->orWhereNotNull('materia_requisito.id');
            })
            ->select(
                'requisito.id as requisito_id',
                'requisito.nombre',
                'requisito.descripcion',
                DB::raw('CASE WHEN materia_requisito.id IS NOT NULL THEN 1 ELSE 0 END as asignado'),
                'materia_requisito.obligatorio',
                'materia_requisito.estado as relacion_estado'
            )
            ->get();
            
        return response()->json($requisitosMateria);
    }

    public function syncMateriaRequisitos(Request $request, $materiaId)
    {
        $request->validate([
            'asignaciones' => 'required|array',
            'asignaciones.*.requisito_id' => 'required|exists:requisito,id',
            'asignaciones.*.obligatorio' => 'required|boolean',
            'asignaciones.*.estado' => 'required|in:Activo,Inactivo'
        ]);

        DB::beginTransaction();
        try {
            // Eliminar asignaciones previas
            \Modules\P4OfertaAcademica\Models\MateriaRequisito::where('id_materia', $materiaId)->delete();

            // Insertar nuevas asignaciones
            $insertData = [];
            foreach ($request->asignaciones as $asig) {
                $insertData[] = [
                    'id_materia' => $materiaId,
                    'id_requisito' => $asig['requisito_id'],
                    'obligatorio' => $asig['obligatorio'],
                    'estado' => $asig['estado'],
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            if (!empty($insertData)) {
                \Modules\P4OfertaAcademica\Models\MateriaRequisito::insert($insertData);
            }

            DB::commit();
            return response()->json(['message' => 'Asignaciones actualizadas correctamente.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al guardar asignaciones: ' . $e->getMessage()], 500);
        }
    }

    // --- 2. GESTIÓN DE REQUISITOS ENLAZADOS A POSTULANTES ---

    public function index(Request $request)
    {
        $query = DB::table('postulante_requisito as pr')
            ->join('requisito', 'pr.id_requisito', '=', 'requisito.id')
            ->join('persona as postulante', 'pr.id_postulante', '=', 'postulante.id')
            ->select(
                DB::raw("pr.id_postulante || '-' || pr.id_requisito as id"), // Compatible con PostgreSQL
                'pr.id_postulante',
                'pr.id_requisito',
                'requisito.nombre',
                'pr.estado',
                'pr.observacion',
                'requisito.descripcion',
                'postulante.nombre as nombre_postulante',
                'postulante.ci as ci_postulante',
                'pr.created_at'
            );

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where('postulante.nombre', 'ilike', "%{$search}%")
                  ->orWhere('postulante.ci', 'ilike', "%{$search}%")
                  ->orWhere('requisito.nombre', 'ilike', "%{$search}%");
        }

        return response()->json($query->orderBy('pr.created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_postulante' => 'required|exists:persona,id',
            'id_catalogo' => 'required|exists:requisito,id'
        ]);

        // Validar si el postulante ya tiene este requisito asignado
        $existe = \Modules\P2PostulantesYRequisitos\Models\PostulanteRequisito::query()
            ->where('id_postulante', $request->id_postulante)
            ->where('id_requisito', $request->id_catalogo)
            ->exists();

        if ($existe) {
            return response()->json(['message' => 'El postulante ya tiene asignado este requisito.'], 422);
        }

        \Modules\P2PostulantesYRequisitos\Models\PostulanteRequisito::insert([
            'id_postulante' => $request->id_postulante,
            'id_requisito' => $request->id_catalogo,
            'fecha_asignacion' => now()->format('Y-m-d'),
            'estado' => $request->estado ?? 'Pendiente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Requisito enlazado al postulante.']);
    }

    public function updateEstado(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|string',
            'observacion' => 'nullable|string|max:255'
        ]);

        $ids = explode('-', $id);
        if (count($ids) != 2) return response()->json(['message' => 'ID inválido'], 400);

        \Modules\P2PostulantesYRequisitos\Models\PostulanteRequisito::query()
            ->where('id_postulante', $ids[0])
            ->where('id_requisito', $ids[1])
            ->update([
                'estado' => $request->estado,
                'observacion' => $request->observacion,
                'updated_at' => now()
            ]);

        return response()->json(['message' => "Requisito marcado como {$request->estado}"]);
    }

    public function destroy($id)
    {
        $ids = explode('-', $id);
        if (count($ids) != 2) return response()->json(['message' => 'ID inválido'], 400);

        \Modules\P2PostulantesYRequisitos\Models\PostulanteRequisito::query()
            ->where('id_postulante', $ids[0])
            ->where('id_requisito', $ids[1])
            ->delete();

        return response()->json(['message' => 'Enlace de requisito eliminado.']);
    }
}
