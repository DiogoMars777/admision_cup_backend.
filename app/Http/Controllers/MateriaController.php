<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MateriaController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('materia')->select('id', 'nombre', 'descripcion', 'estado');

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where('nombre', 'ilike', "%{$search}%");
        }

        return response()->json($query->orderBy('id', 'desc')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100|unique:materia,nombre',
            'descripcion' => 'nullable|string|max:255',
        ]);

        DB::table('materia')->insert([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'estado' => 'Activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Materia creada exitosamente.']);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'required|string|max:20',
        ]);

        DB::table('materia')->where('id', $id)->update([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'estado' => $request->estado,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Materia actualizada.']);
    }

    public function destroy($id)
    {
        DB::table('materia')->where('id', $id)->delete();
        return response()->json(['message' => 'Materia eliminada.']);
    }
}
