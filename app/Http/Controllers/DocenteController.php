<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DocenteController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('docente')
            ->join('persona', 'docente.id_persona', '=', 'persona.id')
            ->select(
                'persona.id',
                'persona.ci',
                'persona.nombre',
                'persona.sexo',
                'persona.telefono',
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
            $personaId = DB::table('persona')->insertGetId([
                'ci' => $request->ci,
                'nombre' => $request->nombre,
                'sexo' => $request->sexo,
                'telefono' => $request->telefono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('docente')->insert([
                'id_persona' => $personaId,
                'grado_academico' => $request->grado_academico,
                'experiencia_docente' => $request->experiencia_docente,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Crear usuario automáticamente
            $rolDocente = DB::table('rol')->where('nombre', 'Docente')->first();
            if ($rolDocente) {
                DB::table('usuario')->insert([
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
            DB::table('persona')->where('id', $id)->update([
                'nombre' => $request->nombre,
                'telefono' => $request->telefono,
                'updated_at' => now(),
            ]);

            DB::table('docente')->where('id_persona', $id)->update([
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
        DB::table('docente')->where('id_persona', $id)->delete();
        DB::table('persona')->where('id', $id)->delete();
        return response()->json(['message' => 'Docente eliminado.']);
    }
}
