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
                'postulante.colegio',
                'postulante.turno_preferido',
                'postulante.modalidad_preferida'
            );

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('persona.nombre', 'ilike', "%{$search}%")
                  ->orWhere('persona.ci', 'ilike', "%{$search}%");
        }

        $postulantes = $query->orderBy('persona.id', 'desc')->get();

        // Agregar carreras y modalidades de cada postulante
        foreach ($postulantes as $postulante) {
            $carreras = DB::table('postulante_carrera')
                ->join('carrera', 'postulante_carrera.id_carrera', '=', 'carrera.id')
                ->leftJoin('modalidad', 'postulante_carrera.id_modalidad', '=', 'modalidad.id')
                ->where('postulante_carrera.id_postulante', $postulante->id)
                ->select(
                    'carrera.nombre as carrera_nombre',
                    'modalidad.nombre as modalidad_nombre',
                    'postulante_carrera.prioridad'
                )
                ->orderBy('postulante_carrera.prioridad')
                ->get();

            $postulante->carrera1 = '';
            $postulante->modalidad1 = '';
            $postulante->carrera2 = '';
            $postulante->modalidad2 = '';

            foreach ($carreras as $c) {
                if ($c->prioridad == 1) {
                    $postulante->carrera1 = $c->carrera_nombre;
                    $postulante->modalidad1 = $c->modalidad_nombre ?? '';
                } elseif ($c->prioridad == 2) {
                    $postulante->carrera2 = $c->carrera_nombre;
                    $postulante->modalidad2 = $c->modalidad_nombre ?? '';
                }
            }
        }

        return response()->json($postulantes);
    }

    public function store(Request $request)
    {
        $request->validate([
            'ci' => 'required|string|unique:persona,ci',
            'nombre' => 'required|string|max:150',
            'fecha_nac' => 'nullable|date',
            'colegio' => 'nullable|string|max:150',
            'email' => 'nullable|email',
            // Campos académicos
            'turno' => 'nullable|string|max:50',
            'modalidad_preferida' => 'nullable|string|max:50',
            'carrera1' => 'required|string',
            'modalidad1' => 'required|string',
            'carrera2' => 'nullable|string',
            'modalidad2' => 'nullable|string',
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
                'turno_preferido' => $request->turno ?? 'Mañana',
                'modalidad_preferida' => $request->modalidad_preferida ?? 'Presencial',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Guardar Carrera 1
            $this->guardarCarrera($personaId, $request->carrera1, $request->modalidad1, 1);

            // Guardar Carrera 2
            if ($request->carrera2 && $request->modalidad2) {
                $this->guardarCarrera($personaId, $request->carrera2, $request->modalidad2, 2);
            }

            // Usuario automático ELIMINADO según solicitud. Queda pendiente conectar a otro proceso.

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
            'turno' => 'nullable|string|max:50',
            'modalidad_preferida' => 'nullable|string|max:50',
            'carrera1' => 'nullable|string',
            'modalidad1' => 'nullable|string',
            'carrera2' => 'nullable|string',
            'modalidad2' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Actualizar persona
            DB::table('persona')->where('id', $id)->update([
                'nombre' => $request->nombre,
                'sexo' => $request->sexo,
                'telefono' => $request->telefono,
                'updated_at' => now(),
            ]);

            // Actualizar postulante con turno y modalidad preferida
            DB::table('postulante')->where('id_persona', $id)->update([
                'fecha_nac' => $request->fecha_nac,
                'direccion' => $request->direccion,
                'colegio' => $request->colegio,
                'turno_preferido' => $request->turno ?? 'Mañana',
                'modalidad_preferida' => $request->modalidad_preferida ?? 'Presencial',
                'updated_at' => now(),
            ]);

            // Limpiar carreras anteriores y re-insertar
            DB::table('postulante_carrera')->where('id_postulante', $id)->delete();

            // Guardar Carrera 1
            if ($request->carrera1 && $request->modalidad1) {
                $this->guardarCarrera($id, $request->carrera1, $request->modalidad1, 1);
            }

            // Guardar Carrera 2
            if ($request->carrera2 && $request->modalidad2) {
                $this->guardarCarrera($id, $request->carrera2, $request->modalidad2, 2);
            }

            DB::commit();
            return response()->json(['message' => 'Postulante actualizado.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar postulante.', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        DB::table('postulante_carrera')->where('id_postulante', $id)->delete();
        DB::table('postulante')->where('id_persona', $id)->delete();
        DB::table('persona')->where('id', $id)->delete();
        return response()->json(['message' => 'Postulante eliminado.']);
    }

    /**
     * Método reutilizable para guardar una carrera con su modalidad para un postulante.
     * Crea la carrera/modalidad si no existen, asegura la relación modalidad_carrera,
     * e inserta en postulante_carrera.
     */
    private function guardarCarrera(int $postulantId, string $carreraNombre, string $modalidadNombre, int $prioridad): void
    {
        // Buscar o crear carrera
        $carreraId = DB::table('carrera')->where('nombre', $carreraNombre)->value('id');
        if (!$carreraId) {
            $carreraId = DB::table('carrera')->insertGetId([
                'nombre' => $carreraNombre,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Buscar o crear modalidad
        $modalidadId = DB::table('modalidad')->where('nombre', $modalidadNombre)->value('id');
        if (!$modalidadId) {
            $modalidadId = DB::table('modalidad')->insertGetId([
                'nombre' => $modalidadNombre,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Asegurar relación en clase intermedia modalidad_carrera
        $existeRelacion = DB::table('modalidad_carrera')
            ->where('id_carrera', $carreraId)
            ->where('id_modalidad', $modalidadId)
            ->exists();
        if (!$existeRelacion) {
            DB::table('modalidad_carrera')->insert([
                'id_carrera' => $carreraId,
                'id_modalidad' => $modalidadId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Insertar en postulante_carrera
        DB::table('postulante_carrera')->insert([
            'id_postulante' => $postulantId,
            'id_carrera' => $carreraId,
            'id_modalidad' => $modalidadId,
            'prioridad' => $prioridad,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
