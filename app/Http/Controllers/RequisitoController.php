<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequisitoController extends Controller
{
    // --- 1. GESTIÓN DEL CATÁLOGO DE REQUISITOS (BASE) ---
    
    public function getCatalogo()
    {
        return response()->json(DB::table('catalogo_requisito')->get());
    }

    public function storeCatalogo(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:255'
        ]);

        DB::table('catalogo_requisito')->insert([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json(['message' => 'Requisito base creado en el catálogo.']);
    }

    public function deleteCatalogo($id)
    {
        DB::table('catalogo_requisito')->where('id', $id)->delete();
        return response()->json(['message' => 'Requisito base eliminado.']);
    }

    // --- 2. GESTIÓN DE REQUISITOS ENLAZADOS A POSTULANTES ---

    public function index(Request $request)
    {
        $query = DB::table('requisito')
            ->join('persona as postulante', 'requisito.id_postulante', '=', 'postulante.id')
            ->select(
                'requisito.id',
                'requisito.id_postulante',
                'requisito.nombre',
                'requisito.estado',
                'requisito.descripcion',
                'postulante.nombre as nombre_postulante',
                'postulante.ci as ci_postulante',
                'requisito.created_at'
            );

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where('postulante.nombre', 'ilike', "%{$search}%")
                  ->orWhere('postulante.ci', 'ilike', "%{$search}%")
                  ->orWhere('requisito.nombre', 'ilike', "%{$search}%");
        }

        return response()->json($query->orderBy('requisito.id', 'desc')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_postulante' => 'required|exists:persona,id',
            'id_catalogo' => 'required|exists:catalogo_requisito,id'
        ]);

        // Obtener datos del catálogo
        $catalogo = DB::table('catalogo_requisito')->where('id', $request->id_catalogo)->first();

        // Validar si el postulante ya tiene este requisito
        $existe = DB::table('requisito')
            ->where('id_postulante', $request->id_postulante)
            ->where('nombre', $catalogo->nombre)
            ->exists();

        if ($existe) {
            return response()->json(['message' => 'El postulante ya tiene asignado este requisito.'], 422);
        }

        DB::table('requisito')->insert([
            'id_abministrador' => $request->user()->id_persona ?? 1, 
            'id_postulante' => $request->id_postulante,
            'nombre' => $catalogo->nombre,
            'descripcion' => $catalogo->descripcion,
            'estado' => $request->estado ?? 'Pendiente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Requisito enlazado al postulante.']);
    }

    public function updateEstado(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|in:Pendiente,Observado,Validado',
        ]);

        DB::table('requisito')->where('id', $id)->update([
            'estado' => $request->estado,
            'updated_at' => now()
        ]);

        return response()->json(['message' => "Requisito marcado como {$request->estado}"]);
    }

    public function destroy($id)
    {
        DB::table('requisito')->where('id', $id)->delete();
        return response()->json(['message' => 'Enlace de requisito eliminado.']);
    }
}
