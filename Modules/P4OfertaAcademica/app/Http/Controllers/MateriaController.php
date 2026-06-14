<?php

namespace Modules\P4OfertaAcademica\Http\Controllers;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MateriaController extends Controller
{
    public function index(Request $request)
    {
        $query = \Modules\P4OfertaAcademica\Models\Materia::select('id', 'nombre', 'descripcion', 'estado');

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

        \Modules\P4OfertaAcademica\Models\Materia::insert([
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

        \Modules\P4OfertaAcademica\Models\Materia::where('id', $id)->update([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'estado' => $request->estado,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Materia actualizada.']);
    }

    public function destroy($id)
    {
        \Modules\P4OfertaAcademica\Models\Materia::where('id', $id)->delete();
        return response()->json(['message' => 'Materia eliminada.']);
    }
}
