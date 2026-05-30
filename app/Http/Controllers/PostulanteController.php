<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PostulanteController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('postulante')
            ->join('persona', 'postulante.id_persona', '=', 'persona.id')
            ->select(
                'persona.id',
                'persona.ci',
                'persona.nombre',
                'persona.sexo',
                'persona.telefono',
                'postulante.fecha_nac',
                'postulante.direccion',
                'postulante.colegio'
            );

        if ($request->has('search')) {
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
            'fecha_nac' => 'nullable|date',
            'colegio' => 'nullable|string|max:150',
            'email' => 'required|email|unique:usuario,email',
            'carreras' => 'nullable|array', // IDs de carreras seleccionadas
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

            DB::table('postulante')->insert([
                'id_persona' => $personaId,
                'fecha_nac' => $request->fecha_nac,
                'direccion' => $request->direccion,
                'colegio' => $request->colegio,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($request->has('carreras') && count($request->carreras) > 0) {
                foreach ($request->carreras as $index => $carreraId) {
                    DB::table('postulante_carrera')->insert([
                        'id_postulante' => $personaId,
                        'id_carrera' => $carreraId,
                        'prioridad' => $index + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Crear usuario automáticamente
            $rolPostulante = DB::table('rol')->where('nombre', 'Postulante')->first();
            if ($rolPostulante) {
                DB::table('usuario')->insert([
                    'id_persona' => $personaId,
                    'id_rol' => $rolPostulante->id,
                    'email' => $request->email,
                    'password' => Hash::make($request->ci), // Contraseña es el CI
                    'estado' => 'Activo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Postulante registrado exitosamente.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al registrar postulante.', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string|max:150',
            'fecha_nac' => 'nullable|date',
            'colegio' => 'nullable|string|max:150',
        ]);

        DB::beginTransaction();
        try {
            DB::table('persona')->where('id', $id)->update([
                'nombre' => $request->nombre,
                'sexo' => $request->sexo,
                'telefono' => $request->telefono,
                'updated_at' => now(),
            ]);

            DB::table('postulante')->where('id_persona', $id)->update([
                'fecha_nac' => $request->fecha_nac,
                'direccion' => $request->direccion,
                'colegio' => $request->colegio,
                'updated_at' => now(),
            ]);

            DB::commit();
            return response()->json(['message' => 'Postulante actualizado.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar postulante.'], 500);
        }
    }

    public function destroy($id)
    {
        DB::table('postulante')->where('id_persona', $id)->delete();
        DB::table('persona')->where('id', $id)->delete();
        return response()->json(['message' => 'Postulante eliminado.']);
    }
}
