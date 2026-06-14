<?php

namespace App\Http\Controllers\P2_GestionDePostulantes\CU2_RegistrarPostulante;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PostulanteController extends Controller
{
    public function index(Request $request)
    {
        $query = \App\Models\P2_GestionDePostulantes\Postulante::query()
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
            $carreras = \App\Models\P2_GestionDePostulantes\PostulanteCarrera::query()
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
                    'gestion_academica.año as gestion_anio',
                    'gestion_cup.nombre as cup_nombre'
                )
                ->first();
                
            if ($grupoInfo) {
                $postulante->grupo_asignado = $grupoInfo->grupo_nombre . ' (' . $grupoInfo->grupo_turno . ')';
                $postulante->gestion_asignada = $grupoInfo->cup_nombre . ' - ' . $grupoInfo->gestion_anio;
            } else {
                $postulante->grupo_asignado = null;
                $postulante->gestion_asignada = null;
            }
        }

        return response()->json($postulantes);
    }

    public function getPendientesPago(Request $request)
    {
        // Traer todos los postulantes
        $postulantes = \App\Models\P2_GestionDePostulantes\Postulante::query()
            ->join('persona', 'postulante.id_persona', '=', 'persona.id')
            ->leftJoin('usuario', 'usuario.id_persona', '=', 'persona.id')
            ->select(
                'persona.id',
                'persona.ci',
                'persona.nombre',
                'persona.telefono',
                'postulante.colegio',
                'persona.correo',
                'usuario.estado as estado_usuario'
            )
            ->orderBy('persona.nombre')
            ->get();

        $resultado = [];
        foreach ($postulantes as $p) {
            // Contar requisitos totales y entregados
            $totalReqs = \App\Models\P2_GestionDePostulantes\PostulanteRequisito::query()
                ->where('id_postulante', $p->id)->count();
            $entregados = \App\Models\P2_GestionDePostulantes\PostulanteRequisito::query()
                ->where('id_postulante', $p->id)
                ->where('estado', 'Entregado')->count();

            // Solo incluir si tiene requisitos Y todos están entregados
            if ($totalReqs > 0 && $totalReqs === $entregados) {
                // Buscar si ya tiene pago
                $pago = \App\Models\P2_GestionDePostulantes\Pago::where('id_postulante', $p->id)->latest('fecha')->first();

                $resultado[] = [
                    'id'           => $p->id,
                    'ci'           => $p->ci,
                    'nombre'       => $p->nombre,
                    'telefono'     => $p->telefono,
                    'colegio'      => $p->colegio,
                    'correo'       => $p->correo,
                    'estado_usuario' => $p->estado_usuario,
                    'tiene_pago'   => !is_null($pago),
                    'pago'         => $pago,
                    'docs_total'   => $totalReqs,
                    'docs_entregados' => $entregados,
                ];
            }
        }

        return response()->json($resultado);
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

            $personaId = \App\Models\Shared\Persona::insertGetId([
                'ci' => $request->ci,
                'nombre' => $request->nombre,
                'sexo' => $request->sexo,
                'telefono' => $request->telefono,
                'correo' => $correoGenerado,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            \App\Models\P2_GestionDePostulantes\Postulante::insert([
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
            $requisitos = \App\Models\P2_GestionDePostulantes\Requisito::query()
                ->where('estado', 'Activo')
                ->where(function($q) {
                    $q->where('tipo_requisito', 'Postulante')
                      ->orWhereNull('tipo_requisito');
                })
                ->get();
            foreach ($requisitos as $req) {
                \App\Models\P2_GestionDePostulantes\PostulanteRequisito::insert([
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
            \App\Models\Shared\Persona::where('id', $id)->update([
                'nombre' => $request->nombre,
                'sexo' => $request->sexo,
                'telefono' => $request->telefono,
                'correo' => $request->email,
                'updated_at' => now(),
            ]);

            // Actualizar usuario si existe y si hay correo
            if ($request->email) {
                \App\Models\P1_GestionDeSeguridadYAcceso\Usuario::where('id_persona', $id)->update([
                    'email' => $request->email,
                    'updated_at' => now(),
                ]);
            }

            // Actualizar postulante con turno y modalidad preferida
            \App\Models\P2_GestionDePostulantes\Postulante::where('id_persona', $id)->update([
                'fecha_nac' => $request->fecha_nac,
                'direccion' => $request->direccion,
                'colegio' => $request->colegio,
                'turno_preferido' => $request->turno ?? 'Mañana',
                'modalidad_preferida' => $request->modalidad_preferida ?? 'Presencial',
                'updated_at' => now(),
            ]);

            // Limpiar carreras anteriores y re-insertar
            \App\Models\P2_GestionDePostulantes\PostulanteCarrera::where('id_postulante', $id)->delete();

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
        \App\Models\P2_GestionDePostulantes\PostulanteCarrera::where('id_postulante', $id)->delete();
        \App\Models\P2_GestionDePostulantes\Postulante::where('id_persona', $id)->delete();
        \App\Models\Shared\Persona::where('id', $id)->delete();
        return response()->json(['message' => 'Postulante eliminado.']);
    }

    public function pagar($id)
    {
        DB::beginTransaction();
        try {
            // Verificar si el usuario ya está activo
            $usuarioExistente = \App\Models\P1_GestionDeSeguridadYAcceso\Usuario::where('id_persona', $id)->first();
            if ($usuarioExistente && $usuarioExistente->estado === 'Activo') {
                return response()->json(['message' => 'El postulante ya tiene una cuenta activa.'], 400);
            }

            // Generar Comprobante simulado
            $comprobanteId = \App\Models\P2_GestionDePostulantes\Comprobante::insertGetId([
                'nro_comprobante' => 'COMP-' . strtoupper(uniqid()),
                'fecha_emision' => now()->format('Y-m-d'),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Registrar el Pago
            \App\Models\P2_GestionDePostulantes\Pago::insert([
                'id_postulante' => $id,
                'id_comprobante' => $comprobanteId,
                'monto' => 300.00,
                'modalidad_pago' => 'Pasarela Virtual',
                'codigo_transaccion' => 'TXN-' . rand(10000, 99999),
                'estado' => 'Procesado',
                'fecha' => now()->format('Y-m-d'),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Crear y habilitar Usuario y enviarle sus credenciales
            $persona = \App\Models\Shared\Persona::where('id', $id)->first();
            if (!$usuarioExistente) {
                $rolPostulanteId = \App\Models\P1_GestionDeSeguridadYAcceso\Rol::where('nombre', 'Postulante')->value('id');
                
                \App\Models\P1_GestionDeSeguridadYAcceso\Usuario::insert([
                    'id_persona' => $id,
                    'id_rol' => $rolPostulanteId,
                    'email' => $persona->correo,
                    'password' => Hash::make($persona->ci),
                    'estado' => 'Activo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                \App\Models\P1_GestionDeSeguridadYAcceso\Usuario::where('id_persona', $id)->update([
                    'estado' => 'Activo',
                    'updated_at' => now(),
                ]);
            }
            $usuario = \App\Models\P1_GestionDeSeguridadYAcceso\Usuario::where('id_persona', $id)->first();

            // Descontar cupo de la carrera elegida (prioridad 1)
            $carreraElegida = \App\Models\P2_GestionDePostulantes\PostulanteCarrera::where('id_postulante', $id)->orderBy('prioridad')->first();
                if ($carreraElegida) {
                    $gestionActiva = \App\Models\P3_GestionAcademicaBase\GestionAcademica::where('estado', 'Activo')->first();
                    if ($gestionActiva) {
                        \App\Models\P3_GestionAcademicaBase\CupoCarrera::query()
                            ->where('id_carrera', $carreraElegida->id_carrera)
                            ->where('id_gestionacademica', $gestionActiva->id)
                            ->where('cupo_disp', '>', 0)
                            ->decrement('cupo_disp');

                        // Asignar la gestión activa al postulante
                        \App\Models\P2_GestionDePostulantes\Postulante::where('id_persona', $id)
                            ->update(['id_gestionacademica' => $gestionActiva->id]);

                        // 1. Registrar al postulante en la tabla Admisión (Vincular postulante y gestión)
                        $existeAdmision = DB::table('admision')
                            ->where('id_postulante', $id)
                            ->where('id_gestionacademica', $gestionActiva->id)
                            ->exists();
                            
                        if (!$existeAdmision) {
                            DB::table('admision')->insert([
                                'id_postulante' => $id,
                                'id_gestionacademica' => $gestionActiva->id,
                                'id_carrera' => $carreraElegida->id_carrera,
                                'estado' => 'Registrado',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        // 2. Generar automáticamente los registros de NOTA para este postulante
                        // vinculando: programacion_evaluacion, materia y postulante
                        $programaciones = DB::table('programacion_evaluacion')
                            ->where('id_gestionacademica', $gestionActiva->id)
                            ->get();

                        foreach ($programaciones as $prog) {
                            $existeNota = DB::table('nota')
                                ->where('id_postulante', $id)
                                ->where('id_programacion_evaluacion', $prog->id)
                                ->exists();

                            if (!$existeNota) {
                                DB::table('nota')->insert([
                                    'id_postulante' => $id,
                                    'id_programacion_evaluacion' => $prog->id,
                                    'id_materia' => $prog->id_materia,
                                    'puntaje_obtenido' => null,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }
                        }
                    }
                }

                // Enviar el correo real con las credenciales
                try {
                    $htmlContent = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 8px;'>
                        <h2 style='color: #1e3a8a; text-align: center;'>¡Bienvenido a la UAGRM CUP!</h2>
                        <p style='color: #374151; font-size: 16px;'>Estimado/a <b>{$persona->nombre}</b>,</p>
                        <p style='color: #374151; font-size: 16px;'>Su pago de matrícula ha sido procesado exitosamente y su cuenta ha sido habilitada en nuestro sistema.</p>
                        <div style='background-color: #f3f4f6; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                            <p style='margin: 0 0 10px 0; color: #111827;'><b>Sus credenciales de acceso son:</b></p>
                            <p style='margin: 0 0 5px 0; color: #374151;'><b>Usuario / Correo:</b> {$usuario->email}</p>
                            <p style='margin: 0; color: #374151;'><b>Contraseña:</b> {$persona->ci}</p>
                        </div>
                        <p style='color: #6b7280; font-size: 14px; text-align: center; margin-top: 30px;'>
                            Atentamente,<br><b>Dirección de Admisión CUP UAGRM</b>
                        </p>
                    </div>
                    ";

                    \Illuminate\Support\Facades\Mail::html($htmlContent, function ($message) use ($usuario) {
                        $message->to($usuario->email)
                                ->subject('Credenciales de Acceso y Confirmación de Pago - UAGRM CUP');
                    });
                    
                    \Illuminate\Support\Facades\Log::info("Correo de credenciales enviado exitosamente a: {$usuario->email}");
                } catch (\Exception $mailError) {
                    \Illuminate\Support\Facades\Log::error("Error enviando correo a {$usuario->email}: " . $mailError->getMessage());
                    // Continuamos con el proceso aunque el correo falle
                }

            DB::commit();
            return response()->json(['message' => 'Pago procesado y usuario habilitado exitosamente.']);
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error("PAGAR ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['message' => 'Error al procesar el pago.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Método reutilizable para guardar una carrera con su modalidad para un postulante.
     * Crea la carrera/modalidad si no existen, asegura la relación modalidad_carrera,
     * e inserta en postulante_carrera.
     */
    private function guardarCarrera(int $postulantId, string $carreraNombre, string $modalidadNombre, int $prioridad): void
    {
        // Buscar o crear carrera
        $carreraId = \App\Models\P3_GestionAcademicaBase\Carrera::where('nombre', $carreraNombre)->value('id');
        if (!$carreraId) {
            $carreraId = \App\Models\P3_GestionAcademicaBase\Carrera::insertGetId([
                'nombre' => $carreraNombre,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Buscar o crear modalidad
        $modalidadId = \App\Models\P3_GestionAcademicaBase\Modalidad::where('nombre', $modalidadNombre)->value('id');
        if (!$modalidadId) {
            $modalidadId = \App\Models\P3_GestionAcademicaBase\Modalidad::insertGetId([
                'nombre' => $modalidadNombre,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Asegurar relación en clase intermedia modalidad_carrera
        $existeRelacion = \App\Models\P3_GestionAcademicaBase\ModalidadCarrera::query()
            ->where('id_carrera', $carreraId)
            ->where('id_modalidad', $modalidadId)
            ->exists();
        if (!$existeRelacion) {
            \App\Models\P3_GestionAcademicaBase\ModalidadCarrera::insert([
                'id_carrera' => $carreraId,
                'id_modalidad' => $modalidadId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Insertar en postulante_carrera
        \App\Models\P2_GestionDePostulantes\PostulanteCarrera::insert([
            'id_postulante' => $postulantId,
            'id_carrera' => $carreraId,
            'id_modalidad' => $modalidadId,
            'prioridad' => $prioridad,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
