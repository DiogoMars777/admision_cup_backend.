<?php

namespace Modules\P3GestionDePagos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PagoController extends Controller
{
    public function getPendientesPago(Request $request)
    {
        try {
            // Traer todos los postulantes
            $postulantes = \Modules\P2PostulantesYRequisitos\Models\Postulante::query()
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

            $postulantesIds = $postulantes->pluck('id')->toArray();

            // Bulk fetch de conteo de requisitos totales y entregados
            $requisitosTotales = DB::table('postulante_requisito')
                ->whereIn('id_postulante', $postulantesIds)
                ->select('id_postulante', DB::raw('count(*) as total'))
                ->groupBy('id_postulante')
                ->pluck('total', 'id_postulante');

            $requisitosEntregados = DB::table('postulante_requisito')
                ->whereIn('id_postulante', $postulantesIds)
                ->where('estado', 'Entregado')
                ->select('id_postulante', DB::raw('count(*) as entregados'))
                ->groupBy('id_postulante')
                ->pluck('entregados', 'id_postulante');

            // Bulk fetch del último pago por postulante
            // Usamos subquery o fetch all y agrupamos
            $todosLosPagos = \Modules\P3GestionDePagos\Models\Pago::whereIn('id_postulante', $postulantesIds)
                ->orderBy('fecha', 'desc')
                ->get()
                ->groupBy('id_postulante');

            $resultado = [];
            foreach ($postulantes as $p) {
                $totalReqs = isset($requisitosTotales[$p->id]) ? $requisitosTotales[$p->id] : 0;
                $entregados = isset($requisitosEntregados[$p->id]) ? $requisitosEntregados[$p->id] : 0;

                // Solo incluir si tiene requisitos Y todos están entregados
                if ($totalReqs > 0 && $totalReqs === $entregados) {
                    $pago = isset($todosLosPagos[$p->id]) ? $todosLosPagos[$p->id]->first() : null;

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
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al listar pagos de postulantes',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function pagar($id)
    {
        DB::beginTransaction();
        try {
            // Verificar si el usuario ya está activo
            $usuarioExistente = \Modules\P1SeguridadYAuditoria\Models\Usuario::where('id_persona', $id)->first();
            if ($usuarioExistente && $usuarioExistente->estado === 'Activo') {
                return response()->json(['message' => 'El postulante ya tiene una cuenta activa.'], 400);
            }

            // Generar Comprobante simulado
            $comprobanteId = \Modules\P3GestionDePagos\Models\Comprobante::insertGetId([
                'nro_comprobante' => 'COMP-' . strtoupper(uniqid()),
                'fecha_emision' => now()->format('Y-m-d'),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Registrar el Pago
            \Modules\P3GestionDePagos\Models\Pago::insert([
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
            $persona = \Modules\P1SeguridadYAuditoria\Models\Persona::where('id', $id)->first();
            if (!$usuarioExistente) {
                $rolPostulanteId = \Modules\P1SeguridadYAuditoria\Models\Rol::where('nombre', 'Postulante')->value('id');
                
                \Modules\P1SeguridadYAuditoria\Models\Usuario::insert([
                    'id_persona' => $id,
                    'id_rol' => $rolPostulanteId,
                    'email' => $persona->correo,
                    'password' => Hash::make($persona->ci),
                    'estado' => 'Activo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                \Modules\P1SeguridadYAuditoria\Models\Usuario::where('id_persona', $id)->update([
                    'estado' => 'Activo',
                    'updated_at' => now(),
                ]);
            }
            $usuario = \Modules\P1SeguridadYAuditoria\Models\Usuario::where('id_persona', $id)->first();

            // Asignar la gestión activa al postulante
            $carreraElegida = \Modules\P2PostulantesYRequisitos\Models\PostulanteCarrera::where('id_postulante', $id)->orderBy('prioridad')->first();
                if ($carreraElegida) {
                    $gestionActiva = \Modules\P6PlanificacionAcademica\Models\GestionAcademica::where('estado', 'Activo')->first();
                    if ($gestionActiva) {
                        // Asignar la gestión activa al postulante
                        \Modules\P2PostulantesYRequisitos\Models\Postulante::where('id_persona', $id)
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
}
