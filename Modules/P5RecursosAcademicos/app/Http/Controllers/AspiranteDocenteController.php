<?php

namespace Modules\P5RecursosAcademicos\Http\Controllers;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class AspiranteDocenteController extends Controller
{
    public function index(Request $request)
    {
        $query = \Modules\P5RecursosAcademicos\Models\AspiranteDocente::query()
            ->join('persona', 'aspirante_docente.id_persona', '=', 'persona.id')
            ->select(
                'persona.id',
                'persona.ci',
                'persona.nombre',
                'persona.sexo',
                'persona.telefono',
                'aspirante_docente.grado_academico',
                'aspirante_docente.experiencia',
                'aspirante_docente.estado'
            );

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('persona.nombre', 'ilike', "%{$search}%")
                  ->orWhere('persona.ci', 'ilike', "%{$search}%");
        }

        $aspirantes = $query->orderBy('persona.id', 'desc')->get();

        foreach ($aspirantes as $aspirante) {
            $postulaciones = \Modules\P5RecursosAcademicos\Models\PostulacionDocente::query()
                ->join('materia', 'postulacion_docente.id_materia', '=', 'materia.id')
                ->where('postulacion_docente.id_aspirante_docente', $aspirante->id)
                ->select(
                    'postulacion_docente.id',
                    'materia.id as id_materia',
                    'materia.nombre as materia_nombre',
                    'postulacion_docente.fecha_postulacion',
                    'postulacion_docente.estado'
                )
                ->get();
            
            $aspirante->materias = $postulaciones;
            $aspirante->cantidad_materias = count($postulaciones);
            $aspirante->email = \Modules\P1SeguridadYAuditoria\Models\Usuario::where('id_persona', $aspirante->id)->value('email') ?? '';
        }

        return response()->json($aspirantes);
    }

    public function getMateriasPostuladas($id)
    {
        $postulaciones = \Modules\P5RecursosAcademicos\Models\PostulacionDocente::query()
            ->join('materia', 'postulacion_docente.id_materia', '=', 'materia.id')
            ->where('postulacion_docente.id_aspirante_docente', $id)
            ->select(
                'postulacion_docente.id as id_postulacion',
                'materia.id as id_materia',
                'materia.nombre',
                'postulacion_docente.fecha_postulacion',
                'postulacion_docente.estado'
            )
            ->get();
            
        return response()->json($postulaciones);
    }

    public function getRequisitosMateria($idAspirante, $idMateria)
    {
        $postulacion = \Modules\P5RecursosAcademicos\Models\PostulacionDocente::where('id_aspirante_docente', $idAspirante)
            ->where('id_materia', $idMateria)
            ->first();
            
        $idPostulacion = $postulacion ? $postulacion->id : 0;

        $requisitos = \Modules\P4OfertaAcademica\Models\MateriaRequisito::query()
            ->join('requisito', 'materia_requisito.id_requisito', '=', 'requisito.id')
            ->leftJoin('aspirante_requisito', function ($join) use ($idPostulacion) {
                $join->on('materia_requisito.id', '=', 'aspirante_requisito.id_materia_requisito')
                     ->where('aspirante_requisito.id_postulacion_docente', '=', $idPostulacion);
            })
            ->where('materia_requisito.id_materia', $idMateria)
            ->select(
                'materia_requisito.id as id_materia_requisito',
                'requisito.nombre as requisito_nombre',
                'requisito.descripcion',
                'materia_requisito.obligatorio',
                DB::raw('COALESCE(aspirante_requisito.cumple, false) as cumplido'),
                DB::raw("COALESCE(aspirante_requisito.estado, 'Pendiente') as estado")
            )
            ->get();
            
        return response()->json($requisitos);
    }

    public function toggleRequisito(Request $request)
    {
        $request->validate([
            'id_aspirante' => 'required|integer',
            'id_materia_requisito' => 'required|integer',
            'cumplido' => 'required|boolean'
        ]);

        $idMateria = \Modules\P4OfertaAcademica\Models\MateriaRequisito::where('id', $request->id_materia_requisito)->value('id_materia');
        
        $postulacion = \Modules\P5RecursosAcademicos\Models\PostulacionDocente::firstOrCreate([
            'id_aspirante_docente' => $request->id_aspirante,
            'id_materia' => $idMateria
        ], [
            'fecha_postulacion' => now(),
            'estado' => 'Pendiente'
        ]);

        \Modules\P5RecursosAcademicos\Models\AspiranteRequisito::updateOrInsert(
            [
                'id_postulacion_docente' => $postulacion->id,
                'id_materia_requisito' => $request->id_materia_requisito
            ],
            [
                'cumple' => $request->cumplido,
                'fecha_revision' => now(),
                'estado' => $request->cumplido ? 'Cumplido' : 'Pendiente',
                'updated_at' => now()
            ]
        );

        // Actualizar estado de la postulación si todos están cumplidos
        $this->actualizarEstadoPostulacion($request->id_aspirante, $postulacion->id, $idMateria);

        return response()->json(['message' => 'Requisito actualizado']);
    }

    private function actualizarEstadoPostulacion($idAspirante, $idPostulacion, $idMateria)
    {
        $requisitos = \Modules\P4OfertaAcademica\Models\MateriaRequisito::query()
            ->where('id_materia', $idMateria)
            ->where('obligatorio', true)
            ->get();

        $cumplidos = \Modules\P5RecursosAcademicos\Models\AspiranteRequisito::query()
            ->join('materia_requisito', 'aspirante_requisito.id_materia_requisito', '=', 'materia_requisito.id')
            ->where('aspirante_requisito.id_postulacion_docente', $idPostulacion)
            ->where('materia_requisito.id_materia', $idMateria)
            ->where('materia_requisito.obligatorio', true)
            ->where('aspirante_requisito.cumple', true)
            ->count();

        $estado = 'En preparación';
        if (count($requisitos) > 0 && count($requisitos) == $cumplidos) {
            $estado = 'Aprobada';
        } else if ($cumplidos > 0) {
            $estado = 'En revisión';
        }

        $postulacionActual = \Modules\P5RecursosAcademicos\Models\PostulacionDocente::query()
            ->where('id_aspirante_docente', $idAspirante)
            ->where('id_materia', $idMateria)
            ->first();

        if (!$postulacionActual) return;

        \Modules\P5RecursosAcademicos\Models\PostulacionDocente::query()
            ->where('id', $postulacionActual->id)
            ->update(['estado' => $estado]);

        // Si la materia acaba de ser aprobada y el usuario ya es Docente Oficial
        if ($estado === 'Aprobada' && $postulacionActual->estado !== 'Aprobada') {
            $aspirante = \Modules\P5RecursosAcademicos\Models\AspiranteDocente::where('id_persona', $idAspirante)->first();
            
            if ($aspirante && $aspirante->estado === 'Docente Oficial') {
                // Registrar la materia en la tabla docente_materia
                \Modules\P5RecursosAcademicos\Models\DocenteMateria::updateOrInsert(
                    ['id_docente' => $idAspirante, 'id_materia' => $idMateria],
                    ['created_at' => now(), 'updated_at' => now()]
                );

                // Enviar correo de nueva materia asignada (sin contraseñas)
                $usuario = \Modules\P1SeguridadYAuditoria\Models\Usuario::where('id_persona', $idAspirante)->first();
                $persona = \Modules\P1SeguridadYAuditoria\Models\Persona::where('id', $idAspirante)->first();
                $materiaObj = \Modules\P4OfertaAcademica\Models\Materia::where('id', $idMateria)->first();

                if ($usuario && $persona && $materiaObj) {
                    try {
                        $htmlContent = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 0; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden;'>
                            <div style='background: linear-gradient(135deg, #059669 0%, #10b981 100%); padding: 30px 20px; text-align: center;'>
                                <h1 style='color: white; margin: 0 0 8px 0; font-size: 24px;'>📚 ¡Nueva Materia Asignada!</h1>
                            </div>
                            <div style='padding: 30px;'>
                                <p style='color: #374151; font-size: 16px; margin-bottom: 20px;'>Estimado/a <b>{$persona->nombre}</b>,</p>
                                <p style='color: #374151; font-size: 15px; line-height: 1.6;'>
                                    Le informamos que ha cumplido con los requisitos de la materia <b>{$materiaObj->nombre}</b> y ha sido aceptado para impartirla.
                                    Esta materia ya se encuentra añadida a su carga oficial en el sistema.
                                </p>
                            </div>
                        </div>
                        ";
                        \Illuminate\Support\Facades\Mail::html($htmlContent, function ($message) use ($usuario, $materiaObj) {
                            $message->to($usuario->email)
                                    ->subject('📚 Nueva materia aprobada: ' . $materiaObj->nombre);
                        });
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::warning('Error al enviar correo de nueva materia: ' . $e->getMessage());
                    }
                }
            }
        }
    }

    public function postularMateria(Request $request)
    {
        $request->validate([
            'id_aspirante' => 'required|integer',
            'id_materia' => 'required|integer'
        ]);

        $existe = \Modules\P5RecursosAcademicos\Models\PostulacionDocente::query()
            ->where('id_aspirante_docente', $request->id_aspirante)
            ->where('id_materia', $request->id_materia)
            ->exists();

        if ($existe) {
            return response()->json(['message' => 'Ya está postulado a esta materia'], 400);
        }

        \Modules\P5RecursosAcademicos\Models\PostulacionDocente::insert([
            'id_aspirante_docente' => $request->id_aspirante,
            'id_materia' => $request->id_materia,
            'fecha_postulacion' => now(),
            'estado' => 'En preparación',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json(['message' => 'Postulación registrada exitosamente']);
    }

    public function createAspirante(Request $request)
    {
        $request->validate([
            'ci' => 'required|string|unique:persona,ci',
            'nombre' => 'required|string',
            'email' => 'required|email|unique:usuario,email',
            'telefono' => 'nullable|string',
            'sexo' => 'nullable|string|max:1',
            'grado_academico' => 'required|string',
            'experiencia' => 'required|integer|min:0'
        ]);

        DB::beginTransaction();
        try {
            $personaId = \Modules\P1SeguridadYAuditoria\Models\Persona::insertGetId([
                'ci' => $request->ci,
                'nombre' => $request->nombre,
                'sexo' => $request->sexo ?? 'M',
                'telefono' => $request->telefono,
                'correo' => $request->email,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            \Modules\P5RecursosAcademicos\Models\AspiranteDocente::insert([
                'id_persona' => $personaId,
                'fecha_registro' => now(),
                'grado_academico' => $request->grado_academico,
                'experiencia' => $request->experiencia,
                'estado' => 'Activo',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $rolAspirante = \Modules\P1SeguridadYAuditoria\Models\Rol::where('nombre', 'Postulante')->value('id'); // O usar un rol específico
            \Modules\P1SeguridadYAuditoria\Models\Usuario::insert([
                'id_persona' => $personaId,
                'id_rol' => $rolAspirante,
                'email' => $request->email,
                'password' => Hash::make($request->ci),
                'estado' => 'Activo',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();
            return response()->json(['message' => 'Aspirante registrado exitosamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateAspirante(Request $request, $id)
    {
        $request->validate([
            'ci' => 'required|string|unique:persona,ci,' . $id,
            'nombre' => 'required|string',
            'email' => 'required|email|unique:usuario,email,' . $id . ',id_persona',
            'telefono' => 'nullable|string',
            'sexo' => 'nullable|string|max:1',
            'grado_academico' => 'required|string',
            'experiencia' => 'required|integer|min:0'
        ]);

        DB::beginTransaction();
        try {
            \Modules\P1SeguridadYAuditoria\Models\Persona::where('id', $id)->update([
                'ci' => $request->ci,
                'nombre' => $request->nombre,
                'sexo' => $request->sexo ?? 'M',
                'telefono' => $request->telefono,
                'correo' => $request->email,
                'updated_at' => now()
            ]);

            \Modules\P5RecursosAcademicos\Models\AspiranteDocente::where('id_persona', $id)->update([
                'grado_academico' => $request->grado_academico,
                'experiencia' => $request->experiencia,
                'updated_at' => now()
            ]);

            \Modules\P1SeguridadYAuditoria\Models\Usuario::where('id_persona', $id)->update([
                'email' => $request->email,
                'updated_at' => now()
            ]);

            DB::commit();
            return response()->json(['message' => 'Aspirante actualizado exitosamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error', 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteAspirante($id)
    {
        DB::beginTransaction();
        try {
            // Se elimina en cascada por la llave foránea en id_persona o lo eliminamos manualmente
            \Modules\P1SeguridadYAuditoria\Models\Usuario::where('id_persona', $id)->delete();
            \Modules\P5RecursosAcademicos\Models\AspiranteDocente::where('id_persona', $id)->delete();
            \Modules\P1SeguridadYAuditoria\Models\Persona::where('id', $id)->delete();

            DB::commit();
            return response()->json(['message' => 'Aspirante eliminado exitosamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error', 'error' => $e->getMessage()], 500);
        }
    }

    public function convertirADocente($id)
    {
        DB::beginTransaction();
        try {
            // 1. Verificar si ya es docente
            $esDocente = \Modules\P5RecursosAcademicos\Models\Docente::where('id_persona', $id)->exists();
            if ($esDocente) {
                return response()->json(['message' => 'El aspirante ya es docente'], 400);
            }

            // Obtener datos del aspirante
            $aspirante = \Modules\P5RecursosAcademicos\Models\AspiranteDocente::where('id_persona', $id)->first();
            if (!$aspirante) {
                return response()->json(['message' => 'Aspirante no encontrado'], 404);
            }

            $persona = \Modules\P1SeguridadYAuditoria\Models\Persona::where('id', $id)->first();
            if (!$persona) {
                return response()->json(['message' => 'Persona no encontrada'], 404);
            }

            // 1. Verificar automáticamente que al menos UNA materia tiene TODOS sus requisitos obligatorios cumplidos
            $postulaciones = \Modules\P5RecursosAcademicos\Models\PostulacionDocente::query()
                ->where('id_aspirante_docente', $id)
                ->get();

            if ($postulaciones->isEmpty()) {
                return response()->json(['message' => 'El aspirante no tiene materias postuladas'], 400);
            }

            $materiasAprobadas = [];
            foreach ($postulaciones as $postulacion) {
                $reqObligatorios = \Modules\P4OfertaAcademica\Models\MateriaRequisito::query()
                    ->where('id_materia', $postulacion->id_materia)
                    ->where('obligatorio', true)
                    ->count();

                $reqCumplidos = \Modules\P5RecursosAcademicos\Models\AspiranteRequisito::query()
                    ->join('materia_requisito', 'aspirante_requisito.id_materia_requisito', '=', 'materia_requisito.id')
                    ->where('aspirante_requisito.id_postulacion_docente', $postulacion->id)
                    ->where('materia_requisito.id_materia', $postulacion->id_materia)
                    ->where('materia_requisito.obligatorio', true)
                    ->where('aspirante_requisito.cumple', true)
                    ->count();

                // Si no hay requisitos obligatorios o todos están cumplidos → aprobada
                if ($reqObligatorios == 0 || $reqObligatorios == $reqCumplidos) {
                    $materiasAprobadas[] = $postulacion;
                    // Actualizar estado de la postulación a Aprobada
                    \Modules\P5RecursosAcademicos\Models\PostulacionDocente::query()
                        ->where('id', $postulacion->id)
                        ->update(['estado' => 'Aprobada']);
                }
            }

            if (empty($materiasAprobadas)) {
                return response()->json(['message' => 'El aspirante no tiene ninguna materia con todos los requisitos obligatorios cumplidos. Debe completar al menos una.'], 400);
            }

            // 2. Cambiar estado del aspirante a Docente Oficial
            \Modules\P5RecursosAcademicos\Models\AspiranteDocente::where('id_persona', $id)->update([
                'estado' => 'Docente Oficial',
                'updated_at' => now()
            ]);

            // 7. Copiar atributos del aspirante_docente a la tabla docente
            \Modules\P5RecursosAcademicos\Models\Docente::insert([
                'id_persona' => $id,
                'grado_academico' => $aspirante->grado_academico ?? 'Licenciatura',
                'experiencia_docente' => $aspirante->experiencia ?? 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Registrar materias aprobadas en docente_materia
            foreach ($materiasAprobadas as $materia) {
                \Modules\P5RecursosAcademicos\Models\DocenteMateria::updateOrInsert(
                    ['id_docente' => $id, 'id_materia' => $materia->id_materia],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }

            // 3. Crear/Actualizar usuario: username=CI, password=email(Gmail)
            $usuario = \Modules\P1SeguridadYAuditoria\Models\Usuario::where('id_persona', $id)->first();
            $email = $usuario ? $usuario->email : $persona->correo;

            // 4. Asignar rol Docente
            $rolDocente = \Modules\P1SeguridadYAuditoria\Models\Rol::where('nombre', 'Docente')->value('id');
            if (!$rolDocente) {
                // Crear el rol si no existe
                $rolDocente = \Modules\P1SeguridadYAuditoria\Models\Rol::insertGetId([
                    'nombre' => 'Docente',
                    'descripcion' => 'Rol de docente del sistema',
                    'estado' => 'Activo',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            if ($usuario) {
                // Actualizar usuario existente: password = ci del aspirante
                \Modules\P1SeguridadYAuditoria\Models\Usuario::where('id_persona', $id)->update([
                    'id_rol' => $rolDocente,
                    'password' => Hash::make($persona->ci),
                    'estado' => 'Activo',
                    'updated_at' => now()
                ]);
            } else {
                // Crear usuario nuevo
                \Modules\P1SeguridadYAuditoria\Models\Usuario::insert([
                    'id_persona' => $id,
                    'id_rol' => $rolDocente,
                    'email' => $email,
                    'password' => Hash::make($persona->ci),
                    'estado' => 'Activo',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // 5. Enviar correo con credenciales, materias y bienvenida
            $materiasNombres = \Modules\P5RecursosAcademicos\Models\PostulacionDocente::query()
                ->join('materia', 'postulacion_docente.id_materia', '=', 'materia.id')
                ->where('postulacion_docente.id_aspirante_docente', $id)
                ->where('postulacion_docente.estado', 'Aprobada')
                ->pluck('materia.nombre')
                ->toArray();

            $materiasListHtml = '';
            foreach ($materiasNombres as $nombreMateria) {
                $materiasListHtml .= "<li style='padding: 6px 0; color: #374151;'>{$nombreMateria}</li>";
            }

            try {
                $htmlContent = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 0; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden;'>
                    <div style='background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0a4a8e 100%); padding: 30px 20px; text-align: center;'>
                        <h1 style='color: white; margin: 0 0 8px 0; font-size: 24px;'>🎓 ¡Bienvenido al equipo docente!</h1>
                        <p style='color: #93c5fd; margin: 0; font-size: 14px;'>Sistema CUP - Universidad Autónoma</p>
                    </div>

                    <div style='padding: 30px;'>
                        <p style='color: #374151; font-size: 16px; margin-bottom: 20px;'>
                            Estimado/a <b>{$persona->nombre}</b>,
                        </p>
                        <p style='color: #374151; font-size: 15px; line-height: 1.6;'>
                            Nos complace informarle que ha completado exitosamente el proceso de postulación y ha sido habilitado como <b>Docente Oficial</b> en el Sistema CUP.
                        </p>

                        <div style='background-color: #f0fdf4; border: 1px solid #bbf7d0; padding: 20px; border-radius: 10px; margin: 25px 0;'>
                            <p style='margin: 0 0 12px 0; color: #166534; font-weight: bold; font-size: 15px;'>📋 Materias Asignadas:</p>
                            <ul style='margin: 0; padding-left: 20px; list-style-type: disc;'>
                                {$materiasListHtml}
                            </ul>
                        </div>

                        <div style='background-color: #eff6ff; border: 1px solid #bfdbfe; padding: 20px; border-radius: 10px; margin: 25px 0;'>
                            <p style='margin: 0 0 15px 0; color: #1e40af; font-weight: bold; font-size: 15px;'>🔐 Sus credenciales de acceso:</p>
                            <table style='width: 100%;'>
                                <tr>
                                    <td style='padding: 8px 0; color: #6b7280; font-size: 14px; width: 100px;'>Usuario:</td>
                                    <td style='padding: 8px 0; color: #111827; font-weight: bold; font-size: 14px;'>{$email}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Contraseña:</td>
                                    <td style='padding: 8px 0; color: #111827; font-weight: bold; font-size: 14px;'>{$persona->ci}</td>
                                </tr>
                            </table>
                            <p style='margin: 15px 0 0 0; color: #dc2626; font-size: 12px;'>⚠️ Le recomendamos cambiar su contraseña en el primer inicio de sesión.</p>
                        </div>

                        <div style='text-align: center; margin: 30px 0 10px 0;'>
                            <a href='http://localhost:5173/' style='display: inline-block; background: linear-gradient(135deg, #0f172a, #0a4a8e); color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 14px;'>
                                Acceder al Dashboard Docente
                            </a>
                        </div>
                    </div>

                    <div style='background-color: #f9fafb; padding: 20px; text-align: center; border-top: 1px solid #e5e7eb;'>
                        <p style='color: #9ca3af; font-size: 12px; margin: 0;'>
                            Atentamente, <b>Dirección Académica CUP</b><br>
                            Universidad Autónoma Gabriel René Moreno
                        </p>
                    </div>
                </div>
                ";

                Mail::html($htmlContent, function ($message) use ($email, $persona) {
                    $message->to($email)
                            ->subject('🎓 Bienvenido al equipo docente - Sistema CUP');
                });
            } catch (\Exception $mailError) {
                Log::warning('No se pudo enviar correo al nuevo docente: ' . $mailError->getMessage());
            }

            // 6. Commit y respuesta
            DB::commit();
            return response()->json([
                'message' => '¡Aspirante convertido a Docente exitosamente!',
                'materias_aprobadas' => count($materiasAprobadas),
                'email' => $email
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al convertir aspirante a docente: ' . $e->getMessage());
            return response()->json(['message' => 'Error al convertir', 'error' => $e->getMessage()], 500);
        }
    }
}
