<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('usuario')
            ->join('persona', 'usuario.id_persona', '=', 'persona.id')
            ->join('rol', 'usuario.id_rol', '=', 'rol.id')
            ->select(
                'usuario.id',
                'persona.nombre',
                'persona.ci',
                'usuario.email',
                'rol.nombre as rol',
                'usuario.estado'
            );

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('persona.nombre', 'ilike', "%{$search}%")
                  ->orWhere('usuario.email', 'ilike', "%{$search}%")
                  ->orWhere('persona.ci', 'ilike', "%{$search}%");
        }

        return response()->json($query->orderBy('usuario.id', 'desc')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'ci' => 'required|string|unique:persona,ci',
            'nombre' => 'required|string|max:150',
            'email' => 'required|email|unique:usuario,email',
            'password' => 'required|string|min:6',
            'id_rol' => 'required|exists:rol,id',
        ]);

        DB::beginTransaction();
        try {
            $personaId = DB::table('persona')->insertGetId([
                'ci' => $request->ci,
                'nombre' => $request->nombre,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $userId = DB::table('usuario')->insertGetId([
                'id_persona' => $personaId,
                'id_rol' => $request->id_rol,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'estado' => 'Activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Log Bitácora
            $this->logBitacora($request->user()->id, 'CREATE', 'Seguridad', "Usuario {$request->email} creado", $request->ip());

            DB::commit();
            return response()->json(['message' => 'Usuario creado exitosamente.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear usuario.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $usuario = DB::table('usuario')->where('id', $id)->first();
        if (!$usuario) return response()->json(['message' => 'Usuario no encontrado'], 404);

        $request->validate([
            'nombre' => 'required|string|max:150',
            'id_rol' => 'required|exists:rol,id',
        ]);

        DB::beginTransaction();
        try {
            DB::table('persona')->where('id', $usuario->id_persona)->update([
                'nombre' => $request->nombre,
                'updated_at' => now(),
            ]);

            $updateData = [
                'id_rol' => $request->id_rol,
                'updated_at' => now(),
            ];

            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            DB::table('usuario')->where('id', $id)->update($updateData);

            $this->logBitacora($request->user()->id, 'UPDATE', 'Seguridad', "Usuario ID {$id} actualizado", $request->ip());

            DB::commit();
            return response()->json(['message' => 'Usuario actualizado exitosamente.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar usuario.'], 500);
        }
    }

    public function toggleStatus(Request $request, $id)
    {
        $usuario = DB::table('usuario')->where('id', $id)->first();
        if (!$usuario) return response()->json(['message' => 'Usuario no encontrado'], 404);

        $nuevoEstado = $usuario->estado === 'Activo' ? 'Inactivo' : 'Activo';
        DB::table('usuario')->where('id', $id)->update(['estado' => $nuevoEstado]);

        $this->logBitacora($request->user()->id, 'UPDATE', 'Seguridad', "Estado de usuario ID {$id} cambiado a {$nuevoEstado}", $request->ip());

        return response()->json(['message' => "Usuario marcado como {$nuevoEstado}."]);
    }

    public function destroy(Request $request, $id)
    {
        $usuario = DB::table('usuario')->where('id', $id)->first();
        if (!$usuario) return response()->json(['message' => 'Usuario no encontrado'], 404);

        DB::table('usuario')->where('id', $id)->delete();
        
        $this->logBitacora($request->user()->id, 'DELETE', 'Seguridad', "Usuario ID {$id} eliminado", $request->ip());

        return response()->json(['message' => 'Usuario eliminado permanentemente.']);
    }

    public function getRoles()
    {
        return response()->json(DB::table('rol')->select('id', 'nombre')->get());
    }

    private function logBitacora($idUsuario, $accion, $modulo, $descripcion, $ip)
    {
        DB::table('bitacora')->insert([
            'id_usuario' => $idUsuario,
            'accion' => $accion,
            'modulo' => $modulo,
            'descripcion' => $descripcion,
            'fecha' => now()->toDateString(),
            'hora' => now()->toTimeString(),
            'ip_usuario' => $ip,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
