<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Credenciales incorrectas.'
            ], 401);
        }

        if ($user->estado !== 'Activo') {
            return response()->json([
                'message' => 'El usuario se encuentra inactivo.'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Registrar en Bitácora
        DB::table('bitacora')->insert([
            'id_usuario' => $user->id,
            'accion' => 'Login',
            'modulo' => 'Seguridad',
            'descripcion' => 'Inicio de sesión exitoso.',
            'fecha' => now()->toDateString(),
            'hora' => now()->toTimeString(),
            'ip_usuario' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            DB::table('bitacora')->insert([
                'id_usuario' => $user->id,
                'accion' => 'Logout',
                'modulo' => 'Seguridad',
                'descripcion' => 'Cierre de sesión.',
                'fecha' => now()->toDateString(),
                'hora' => now()->toTimeString(),
                'ip_usuario' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Sesión cerrada correctamente'
        ]);
    }
}
