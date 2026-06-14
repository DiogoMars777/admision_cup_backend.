<?php

namespace Modules\P1SeguridadYAuditoria\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\P1SeguridadYAuditoria\Models\Persona;
use Modules\P1SeguridadYAuditoria\Models\Usuario;
use Modules\P1SeguridadYAuditoria\Models\Rol;
use Modules\P1SeguridadYAuditoria\Models\Administrativo;
use Modules\P1SeguridadYAuditoria\Models\SuperAdministrador;

class AdministrativoController extends Controller
{
    public function index()
    {
        $superAdmins = DB::table('super_administrador')
            ->join('persona', 'super_administrador.id_persona', '=', 'persona.id')
            ->leftJoin('usuario', 'usuario.id_persona', '=', 'persona.id')
            ->select(
                'super_administrador.cargo',
                'super_administrador.estado',
                'persona.id as id_persona',
                'persona.ci',
                'persona.nombre',
                'persona.telefono',
                'persona.correo',
                'usuario.email as usuario_email'
            )
            ->orderBy('persona.id', 'desc')
            ->get();

        $administrativos = DB::table('administrativo')
            ->join('persona', 'administrativo.id_persona', '=', 'persona.id')
            ->leftJoin('usuario', 'usuario.id_persona', '=', 'persona.id')
            ->leftJoin('rol', 'rol.id', '=', 'usuario.id_rol')
            ->select(
                'administrativo.area',
                'administrativo.cargo',
                'administrativo.estado',
                'persona.id as id_persona',
                'persona.ci',
                'persona.nombre',
                'persona.telefono',
                'persona.correo',
                'usuario.email as usuario_email',
                'rol.nombre as rol_nombre'
            )
            ->orderBy('persona.id', 'desc')
            ->get();

        $roles = DB::table('rol')
            ->whereIn('nombre', ['Super Admin', 'Administrador', 'Coordinador'])
            ->select('id', 'nombre')
            ->get();

        return response()->json([
            'super_admins' => $superAdmins,
            'administrativos' => $administrativos,
            'roles' => $roles
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'ci' => 'required|string|unique:persona,ci',
            'nombre' => 'required|string|max:150',
            'correo' => 'required|email',
            'tipo' => 'required|string',
            'cargo' => 'required|string|max:150',
            'password' => 'required|string|min:6'
        ]);

        DB::beginTransaction();
        try {
            // 1. Crear Persona
            $personaId = Persona::insertGetId([
                'ci' => $request->ci,
                'nombre' => $request->nombre,
                'telefono' => $request->telefono,
                'correo' => $request->correo,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // 2. Buscar Rol
            $rol = Rol::where('nombre', $request->tipo)->first();
            if (!$rol) {
                throw new \Exception("Rol no válido");
            }

            // 3. Crear Usuario con el rol
            $usuarioId = Usuario::insertGetId([
                'id_persona' => $personaId,
                'id_rol' => $rol->id,
                'email' => $request->correo,
                'password' => Hash::make($request->password),
                'estado' => 'Activo',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // 4. Crear registro especifico
            if ($request->tipo === 'Super Admin') {
                SuperAdministrador::insert([
                    'id_persona' => $personaId,
                    'cargo' => $request->cargo,
                    'estado' => 'Activo',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

            } else {
                Administrativo::insert([
                    'id_persona' => $personaId,
                    'area' => $request->area ?? 'General',
                    'cargo' => $request->cargo,
                    'estado' => 'Activo',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Personal registrado exitosamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al registrar personal', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string|max:150',
            'correo' => 'required|email',
            'tipo' => 'required|string',
            'cargo' => 'required|string|max:150',
        ]);

        DB::beginTransaction();
        try {
            // El id recibido es el id_persona
            $personaId = $request->id_persona;
            
            Persona::where('id', $personaId)->update([
                'nombre' => $request->nombre,
                'telefono' => $request->telefono,
                'correo' => $request->correo,
                'updated_at' => now()
            ]);

            Usuario::where('id_persona', $personaId)->update([
                'email' => $request->correo,
                'updated_at' => now()
            ]);

            if ($request->password) {
                Usuario::where('id_persona', $personaId)->update([
                    'password' => Hash::make($request->password)
                ]);
            }

            // Update role if changed
            $usuario = Usuario::where('id_persona', $personaId)->first();
            if ($usuario) {
                $rol = Rol::where('nombre', $request->tipo)->first();
                if ($rol) {
                    Usuario::where('id', $usuario->id)->update([
                        'id_rol' => $rol->id,
                        'updated_at' => now()
                    ]);
                }
            }

            if ($request->tipo === 'Super Admin') {
                SuperAdministrador::where('id_persona', $personaId)->update([
                    'cargo' => $request->cargo,
                    'updated_at' => now()
                ]);
            } else {
                Administrativo::where('id_persona', $personaId)->update([
                    'cargo' => $request->cargo,
                    'area' => $request->area ?? 'General',
                    'updated_at' => now()
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Personal actualizado exitosamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar personal', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id, Request $request)
    {
        $tipo = $request->query('tipo');
        // $id is id_persona
        $personaId = $id;
        
        DB::beginTransaction();
        try {
            if ($tipo === 'SuperAdmin') {
                SuperAdministrador::where('id_persona', $personaId)->delete();
            } else {
                Administrativo::where('id_persona', $personaId)->delete();
            }
            
            $usuario = Usuario::where('id_persona', $personaId)->first();
            if ($usuario) {
                $usuario->delete();
            }
            
            Persona::where('id', $personaId)->delete();
            
            DB::commit();
            return response()->json(['message' => 'Personal eliminado exitosamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al eliminar personal', 'error' => $e->getMessage()], 500);
        }
    }
}
