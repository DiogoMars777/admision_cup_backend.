<?php

namespace App\Http\Controllers\P3_GestionAcademicaBase\CU7_GestionarDocentes;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DocenteController extends Controller
{
    public function index(Request $request)
    {
        $query = \App\Models\P3_GestionAcademicaBase\Docente::query()
            ->join('persona', 'docente.id_persona', '=', 'persona.id')
            ->leftJoin('usuario', 'usuario.id_persona', '=', 'persona.id')
            ->select(
                'persona.id',
                'persona.ci',
                'persona.nombre',
                'persona.sexo',
                'persona.telefono',
                'usuario.email as email',
                'docente.grado_academico',
                'docente.experiencia_docente'
            );

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where('persona.nombre', 'ilike', "%{$search}%")
                  ->orWhere('persona.ci', 'ilike', "%{$search}%");
        }

        return response()->json($query->orderBy('persona.id', 'desc')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'ci' => 'required|string|unique:persona,ci',
            'nombre' => 'required|string|max:150',
            'telefono' => 'nullable|string|max:20',
            'sexo' => 'nullable|string|max:10',
            'grado_academico' => 'nullable|string|max:100',
            'experiencia_docente' => 'nullable|integer',
            'email' => 'required|email|unique:usuario,email',
        ]);

        DB::beginTransaction();
        try {
            $personaId = \App\Models\Shared\Persona::insertGetId([
                'ci' => $request->ci,
                'nombre' => $request->nombre,
                'sexo' => $request->sexo,
                'telefono' => $request->telefono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            \App\Models\P3_GestionAcademicaBase\Docente::insert([
                'id_persona' => $personaId,
                'grado_academico' => $request->grado_academico,
                'experiencia_docente' => $request->experiencia_docente,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Crear usuario automáticamente
            $rolDocente = \App\Models\P1_GestionDeSeguridadYAcceso\Rol::where('nombre', 'Docente')->first();
            if ($rolDocente) {
                \App\Models\P1_GestionDeSeguridadYAcceso\Usuario::insert([
                    'id_persona' => $personaId,
                    'id_rol' => $rolDocente->id,
                    'email' => $request->email,
                    'password' => Hash::make($request->ci), // Contraseña es el CI
                    'estado' => 'Activo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Docente registrado exitosamente.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al registrar docente.', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string|max:150',
            'telefono' => 'nullable|string|max:20',
            'grado_academico' => 'nullable|string|max:100',
            'experiencia_docente' => 'nullable|integer',
        ]);

        DB::beginTransaction();
        try {
            \App\Models\Shared\Persona::where('id', $id)->update([
                'nombre' => $request->nombre,
                'telefono' => $request->telefono,
                'updated_at' => now(),
            ]);

            \App\Models\P3_GestionAcademicaBase\Docente::where('id_persona', $id)->update([
                'grado_academico' => $request->grado_academico,
                'experiencia_docente' => $request->experiencia_docente,
                'updated_at' => now(),
            ]);

            DB::commit();
            return response()->json(['message' => 'Docente actualizado.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar docente.'], 500);
        }
    }

    public function destroy($id)
    {
        \App\Models\P3_GestionAcademicaBase\Docente::where('id_persona', $id)->delete();
        \App\Models\Shared\Persona::where('id', $id)->delete();
        return response()->json(['message' => 'Docente eliminado.']);
    }
}
