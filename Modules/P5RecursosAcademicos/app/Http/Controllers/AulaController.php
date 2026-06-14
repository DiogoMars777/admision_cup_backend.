<?php

namespace Modules\P5RecursosAcademicos\Http\Controllers;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AulaController extends Controller
{
    public function index(Request $request)
    {
        $query = \Modules\P5RecursosAcademicos\Models\Aula::select('id', 'aula_nro', 'capacidad', 'tipo_aula');

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where('aula_nro', 'ilike', "%{$search}%")
                  ->orWhere('tipo_aula', 'ilike', "%{$search}%");
        }

        return response()->json($query->orderBy('id', 'desc')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'aula_nro' => 'required|string|max:20|unique:aula,aula_nro',
            'capacidad' => 'required|integer|min:1',
            'tipo_aula' => 'nullable|string|max:50',
        ]);

        \Modules\P5RecursosAcademicos\Models\Aula::insert([
            'aula_nro' => $request->aula_nro,
            'capacidad' => $request->capacidad,
            'tipo_aula' => $request->tipo_aula,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Aula creada exitosamente.']);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'aula_nro' => 'required|string|max:20',
            'capacidad' => 'required|integer|min:1',
            'tipo_aula' => 'nullable|string|max:50',
        ]);

        \Modules\P5RecursosAcademicos\Models\Aula::where('id', $id)->update([
            'aula_nro' => $request->aula_nro,
            'capacidad' => $request->capacidad,
            'tipo_aula' => $request->tipo_aula,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Aula actualizada.']);
    }

    public function destroy($id)
    {
        \Modules\P5RecursosAcademicos\Models\Aula::where('id', $id)->delete();
        return response()->json(['message' => 'Aula eliminada.']);
    }
}
