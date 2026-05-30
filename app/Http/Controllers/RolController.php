<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RolController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('rol');
        if ($request->has('search')) {
            $query->where('nombre', 'ilike', '%' . $request->search . '%')
                  ->orWhere('descripcion', 'ilike', '%' . $request->search . '%');
        }
        return response()->json($query->orderBy('id', 'asc')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:50|unique:rol,nombre',
            'descripcion' => 'nullable|string|max:255'
        ]);

        DB::table('rol')->insert([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Rol creado correctamente.']);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string|max:50|unique:rol,nombre,' . $id,
            'descripcion' => 'nullable|string|max:255'
        ]);

        DB::table('rol')->where('id', $id)->update([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Rol actualizado correctamente.']);
    }

    public function destroy($id)
    {
        // Verificar si hay usuarios usando este rol
        $usersCount = DB::table('usuario')->where('id_rol', $id)->count();
        if ($usersCount > 0) {
            return response()->json(['message' => 'No se puede eliminar el rol porque tiene usuarios asignados.'], 400);
        }

        DB::table('rol')->where('id', $id)->delete();
        return response()->json(['message' => 'Rol eliminado correctamente.']);
    }
}
