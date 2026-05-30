<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GrupoController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('grupo')
            ->join('gestion_academica', 'grupo.id_gestionacademica', '=', 'gestion_academica.id')
            ->select(
                'grupo.id',
                'grupo.nombre',
                'grupo.cupo_max',
                'grupo.cant_estudiante',
                'grupo.modalidad',
                'grupo.turno',
                'grupo.estado',
                'gestion_academica.nombre as gestion'
            );

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where('grupo.nombre', 'ilike', "%{$search}%");
        }

        return response()->json($query->orderBy('grupo.id', 'desc')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_gestionacademica' => 'required|exists:gestion_academica,id',
            'nombre' => 'required|string|max:50',
            'cupo_max' => 'required|integer|min:1',
            'modalidad' => 'nullable|string|max:50',
            'turno' => 'nullable|string|max:50',
        ]);

        DB::table('grupo')->insert([
            'id_gestionacademica' => $request->id_gestionacademica,
            'nombre' => $request->nombre,
            'cupo_max' => $request->cupo_max,
            'cant_estudiante' => 0,
            'modalidad' => $request->modalidad,
            'turno' => $request->turno,
            'estado' => 'Activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Grupo creado exitosamente.']);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string|max:50',
            'cupo_max' => 'required|integer|min:1',
            'modalidad' => 'nullable|string|max:50',
            'turno' => 'nullable|string|max:50',
            'estado' => 'required|string|max:20',
        ]);

        DB::table('grupo')->where('id', $id)->update([
            'nombre' => $request->nombre,
            'cupo_max' => $request->cupo_max,
            'modalidad' => $request->modalidad,
            'turno' => $request->turno,
            'estado' => $request->estado,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Grupo actualizado.']);
    }

    public function destroy($id)
    {
        DB::table('grupo')->where('id', $id)->delete();
        return response()->json(['message' => 'Grupo eliminado.']);
    }

    public function getGestiones()
    {
        return response()->json(DB::table('gestion_academica')->select('id', 'nombre', 'periodo', 'año')->get());
    }
}
