<?php

namespace Modules\P2PostulantesYRequisitos\Http\Controllers;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PostulanteController extends Controller
{
    public function index(Request $request)
    {
        $query = \Modules\P2PostulantesYRequisitos\Models\Postulante::query()
            ->join('persona', 'postulante.id_persona', '=', 'persona.id')
            ->select(
                'persona.id',
                'persona.ci',
                'persona.nombre',
                'persona.sexo',
                'persona.telefono',
                'persona.correo',
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
            $carreras = \Modules\P2PostulantesYRequisitos\Models\PostulanteCarrera::query()
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

            $grupoInfo = DB::table('postulante_grupo')
                ->join('grupo', 'postulante_grupo.id_grupo', '=', 'grupo.id')
                ->join('gestion_academica', 'grupo.id_gestionacademica', '=', 'gestion_academica.id')
                ->join('gestion_cup', 'gestion_academica.id_gestion_cup', '=', 'gestion_cup.id')
                ->where('postulante_grupo.id_postulante', $postulante->id)
                ->select(
                    'grupo.nombre as grupo_nombre',
                    'grupo.turno as grupo_turno',
                    'gestion_academica.id as gestion_id',
                    'gestion_academica.año as gestion_anio',
                    'gestion_cup.nombre as cup_nombre'
                )
                ->first();
                
            if ($grupoInfo) {
                $postulante->grupo_asignado = $grupoInfo->grupo_nombre . ' (' . $grupoInfo->grupo_turno . ')';
                $postulante->gestion_asignada = $grupoInfo->cup_nombre . ' - ' . $grupoInfo->gestion_anio;
                
                // Buscar estado de admision
                $admision = DB::table('admision')
                    ->leftJoin('carrera', 'admision.id_carrera', '=', 'carrera.id')
                    ->where('admision.id_postulante', $postulante->id)
                    ->where('admision.id_gestionacademica', $grupoInfo->gestion_id)
                    ->select('admision.estado', 'carrera.nombre as carrera_asignada')
                    ->first();
                
                if ($admision) {
                    $postulante->admision_estado = $admision->estado;
                    $postulante->admision_carrera = $admision->carrera_asignada;
                } else {
                    $postulante->admision_estado = null;
                    $postulante->admision_carrera = null;
                }
            } else {
                $postulante->grupo_asignado = null;
                $postulante->gestion_asignada = null;
                $postulante->admision_estado = null;
                $postulante->admision_carrera = null;
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
            $correoGenerado = $request->email ?: strtolower(str_replace(' ', '', explode(' ', $request->nombre)[0])) . $request->ci . '@cup.edu.bo';

            $personaId = \Modules\P1SeguridadYAuditoria\Models\Persona::insertGetId([
                'ci' => $request->ci,
                'nombre' => $request->nombre,
                'sexo' => $request->sexo,
                'telefono' => $request->telefono,
                'correo' => $correoGenerado,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            \Modules\P2PostulantesYRequisitos\Models\Postulante::insert([
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

            // Auto-asignar todos los requisitos activos (tipo Postulante) al nuevo postulante
            $requisitos = \Modules\P2PostulantesYRequisitos\Models\Requisito::query()
                ->where('estado', 'Activo')
                ->where(function($q) {
                    $q->where('tipo_requisito', 'Postulante')
                      ->orWhereNull('tipo_requisito');
                })
                ->get();
            foreach ($requisitos as $req) {
                \Modules\P2PostulantesYRequisitos\Models\PostulanteRequisito::insert([
                    'id_postulante' => $personaId,
                    'id_requisito'  => $req->id,
                    'fecha_asignacion' => now()->format('Y-m-d'),
                    'estado' => 'Pendiente',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // La cuenta de usuario ya no se crea aquí, se creará al realizar el pago.

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
            'email' => 'nullable|email|unique:persona,correo,' . $id,
        ]);

        DB::beginTransaction();
        try {
            // Actualizar persona
            \Modules\P1SeguridadYAuditoria\Models\Persona::where('id', $id)->update([
                'nombre' => $request->nombre,
                'sexo' => $request->sexo,
                'telefono' => $request->telefono,
                'correo' => $request->email,
                'updated_at' => now(),
            ]);

            // Actualizar usuario si existe y si hay correo
            if ($request->email) {
                \Modules\P1SeguridadYAuditoria\Models\Usuario::where('id_persona', $id)->update([
                    'email' => $request->email,
                    'updated_at' => now(),
                ]);
            }

            // Actualizar postulante con turno y modalidad preferida
            \Modules\P2PostulantesYRequisitos\Models\Postulante::where('id_persona', $id)->update([
                'fecha_nac' => $request->fecha_nac,
                'direccion' => $request->direccion,
                'colegio' => $request->colegio,
                'turno_preferido' => $request->turno ?? 'Mañana',
                'modalidad_preferida' => $request->modalidad_preferida ?? 'Presencial',
                'updated_at' => now(),
            ]);

            // Limpiar carreras anteriores y re-insertar
            \Modules\P2PostulantesYRequisitos\Models\PostulanteCarrera::where('id_postulante', $id)->delete();

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
        \Modules\P2PostulantesYRequisitos\Models\PostulanteCarrera::where('id_postulante', $id)->delete();
        \Modules\P2PostulantesYRequisitos\Models\Postulante::where('id_persona', $id)->delete();
        \Modules\P1SeguridadYAuditoria\Models\Persona::where('id', $id)->delete();
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
        $carreraId = \Modules\P4OfertaAcademica\Models\Carrera::where('nombre', $carreraNombre)->value('id');
        if (!$carreraId) {
            $carreraId = \Modules\P4OfertaAcademica\Models\Carrera::insertGetId([
                'nombre' => $carreraNombre,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Buscar o crear modalidad
        $modalidadId = \Modules\P4OfertaAcademica\Models\Modalidad::where('nombre', $modalidadNombre)->value('id');
        if (!$modalidadId) {
            $modalidadId = \Modules\P4OfertaAcademica\Models\Modalidad::insertGetId([
                'nombre' => $modalidadNombre,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Asegurar relación en clase intermedia modalidad_carrera
        $existeRelacion = \Modules\P4OfertaAcademica\Models\ModalidadCarrera::query()
            ->where('id_carrera', $carreraId)
            ->where('id_modalidad', $modalidadId)
            ->exists();
        if (!$existeRelacion) {
            \Modules\P4OfertaAcademica\Models\ModalidadCarrera::insert([
                'id_carrera' => $carreraId,
                'id_modalidad' => $modalidadId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Insertar en postulante_carrera
        \Modules\P2PostulantesYRequisitos\Models\PostulanteCarrera::insert([
            'id_postulante' => $postulantId,
            'id_carrera' => $carreraId,
            'id_modalidad' => $modalidadId,
            'prioridad' => $prioridad,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
