<?php

namespace App\Http\Controllers\P1_GestionDeSeguridadYAcceso\CU01_GestionDeUsuariosYAutenticacion;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\P1_GestionDeSeguridadYAcceso\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AuthController extends Controller
{
    const MAX_ATTEMPTS = 5;
    const LOCKOUT_SECONDS = 300; // 5 minutos

    /**
     * Verificar si un email está bloqueado por intentos fallidos.
     * Retorna [bloqueado, segundos_restantes, intentos_fallidos].
     */
    public static function checkLockout(string $email): array
    {
        $key = 'login_lockout:' . strtolower($email);
        $attemptsKey = 'login_attempts:' . strtolower($email);

        if (Cache::has($key)) {
            $lockoutUntil = Cache::get($key);
            $remaining = max(0, $lockoutUntil - now()->timestamp);
            if ($remaining > 0) {
                return [true, $remaining, self::MAX_ATTEMPTS];
            }
            // El bloqueo expiró, limpiar
            Cache::forget($key);
            Cache::forget($attemptsKey);
        }

        $attempts = Cache::get($attemptsKey, 0);
        return [false, 0, $attempts];
    }

    /**
     * Registrar un intento fallido de login.
     */
    private function registerFailedAttempt(string $email): array
    {
        $attemptsKey = 'login_attempts:' . strtolower($email);
        $attempts = Cache::get($attemptsKey, 0) + 1;
        Cache::put($attemptsKey, $attempts, self::LOCKOUT_SECONDS);

        if ($attempts >= self::MAX_ATTEMPTS) {
            $lockoutKey = 'login_lockout:' . strtolower($email);
            Cache::put($lockoutKey, now()->timestamp + self::LOCKOUT_SECONDS, self::LOCKOUT_SECONDS);
            return [$attempts, self::LOCKOUT_SECONDS];
        }

        return [$attempts, 0];
    }

    /**
     * Limpiar los intentos fallidos tras un login exitoso.
     */
    private function clearAttempts(string $email): void
    {
        Cache::forget('login_attempts:' . strtolower($email));
        Cache::forget('login_lockout:' . strtolower($email));
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $email = $request->email;

        // Verificar bloqueo
        [$locked, $lockoutSeconds, $attempts] = self::checkLockout($email);

        if ($locked) {
            return response()->json([
                'message' => "Cuenta bloqueada temporalmente. Espera antes de intentar de nuevo.",
                'locked' => true,
                'lockout_seconds' => $lockoutSeconds,
                'attempts' => self::MAX_ATTEMPTS,
                'max_attempts' => self::MAX_ATTEMPTS,
            ], 429);
        }

        $user = Usuario::where('email', $email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            [$newAttempts, $lockoutSecs] = $this->registerFailedAttempt($email);
            $remaining = self::MAX_ATTEMPTS - $newAttempts;

            $msg = $remaining > 0
                ? "Credenciales incorrectas. Te quedan {$remaining} intento(s)."
                : "Has excedido el límite de intentos. Cuenta bloqueada por 5 minutos.";

            return response()->json([
                'message' => $msg,
                'locked' => $remaining <= 0,
                'lockout_seconds' => $lockoutSecs,
                'attempts' => $newAttempts,
                'max_attempts' => self::MAX_ATTEMPTS,
                'remaining' => max(0, $remaining),
            ], $remaining <= 0 ? 429 : 401);
        }

        if ($user->estado !== 'Activo') {
            return response()->json([
                'message' => 'El usuario se encuentra inactivo.'
            ], 403);
        }

        // Login exitoso: limpiar intentos
        $this->clearAttempts($email);

        $token = $user->createToken('auth_token')->plainTextToken;

        // Registrar en Bitácora
        \App\Models\P1_GestionDeSeguridadYAcceso\Bitacora::insert([
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

        // Obtener nombre y rol para el frontend
        $persona = \App\Models\Shared\Persona::where('id', $user->id_persona)->first();
        $rol = \App\Models\P1_GestionDeSeguridadYAcceso\Rol::where('id', $user->id_rol)->value('nombre') ?? 'Desconocido';
        
        $userData = $user->toArray();
        $userData['nombre'] = $persona ? $persona->nombre : 'Usuario';
        $userData['ci'] = $persona ? $persona->ci : 'N/A';
        $userData['telefono'] = $persona ? $persona->telefono : 'N/A';
        $userData['rol'] = $rol;

        $gestionActiva = DB::table('gestion_academica')->where('estado', 'Activo')->first();
        $userData['has_active_gestion'] = $gestionActiva ? true : false;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $userData
        ]);
    }

    /**
     * Endpoint público para verificar si un email está bloqueado.
     * Usado por el frontend antes de enviar recuperación de contraseña.
     */
    public function checkLockoutStatus(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        [$locked, $lockoutSeconds, $attempts] = self::checkLockout($request->email);

        return response()->json([
            'locked' => $locked,
            'lockout_seconds' => $lockoutSeconds,
            'attempts' => $attempts,
            'max_attempts' => self::MAX_ATTEMPTS,
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            \App\Models\P1_GestionDeSeguridadYAcceso\Bitacora::insert([
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

