<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\VerificationCodeMail;
use App\Models\User;

class PasswordResetController extends Controller
{
    /**
     * Paso 1: Enviar código de 6 dígitos al correo
     */
    public function sendCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'No existe ningún usuario registrado con ese correo.'
            ], 404);
        }

        // Generar código numérico de 6 dígitos
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Guardar en password_reset_tokens (expira en 15 min)
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token'      => $code,
                'created_at' => now(),
            ]
        );

        // Enviar el correo
        Mail::to($request->email)->send(new VerificationCodeMail($code));

        return response()->json([
            'message' => 'Código enviado a tu correo electrónico.'
        ]);
    }

    /**
     * Paso 2: Verificar el código ingresado
     */
    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code'  => 'required|string|size:6',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->code)
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'El código es incorrecto.'
            ], 422);
        }

        // Verificar que no hayan pasado más de 15 minutos
        $createdAt = \Carbon\Carbon::parse($record->created_at);
        if ($createdAt->diffInMinutes(now()) > 15) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'message' => 'El código ha expirado. Solicita uno nuevo.'
            ], 422);
        }

        return response()->json([
            'message' => 'Código válido.'
        ]);
    }

    /**
     * Paso 3: Establecer nueva contraseña
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'code'     => 'required|string|size:6',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->code)
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'El código es incorrecto o ya fue usado.'
            ], 422);
        }

        $createdAt = \Carbon\Carbon::parse($record->created_at);
        if ($createdAt->diffInMinutes(now()) > 15) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'message' => 'El código ha expirado. Solicita uno nuevo.'
            ], 422);
        }

        // Actualizar contraseña
        User::where('email', $request->email)->update([
            'password' => Hash::make($request->password),
        ]);

        // Eliminar el token usado
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'message' => 'Contraseña actualizada correctamente.'
        ]);
    }
}
