<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AuditLogMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Solo registrar si el usuario está autenticado y la petición es exitosa o cambia datos
        if (Auth::check() && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $user = Auth::user();
            
            $method = $request->method();
            $path = $request->path();
            
            $accion = 'Otra';
            if ($method === 'POST') $accion = 'Crear';
            if ($method === 'PUT' || $method === 'PATCH') $accion = 'Actualizar';
            if ($method === 'DELETE') $accion = 'Eliminar';

            // No loguear login/logout aquí para no duplicar
            if (strpos($path, 'login') !== false || strpos($path, 'logout') !== false) {
                return $response;
            }

            // Mapeo básico de módulos
            $modulo = 'General';
            if (strpos($path, 'materia') !== false) $modulo = 'Materias';
            elseif (strpos($path, 'docente') !== false) $modulo = 'Docentes';
            elseif (strpos($path, 'aula') !== false) $modulo = 'Aulas';
            elseif (strpos($path, 'grupo') !== false) $modulo = 'Grupos';
            elseif (strpos($path, 'postulante') !== false) $modulo = 'Postulantes';
            elseif (strpos($path, 'requisito') !== false) $modulo = 'Requisitos';
            elseif (strpos($path, 'usuario') !== false) $modulo = 'Usuarios';
            elseif (strpos($path, 'rol') !== false) $modulo = 'Roles';

            DB::table('bitacora')->insert([
                'id_usuario' => $user->id,
                'accion' => $accion,
                'modulo' => $modulo,
                'descripcion' => "Operación $accion en endpoint: /$path",
                'fecha' => now()->toDateString(),
                'hora' => now()->toTimeString(),
                'ip_usuario' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $response;
    }
}
