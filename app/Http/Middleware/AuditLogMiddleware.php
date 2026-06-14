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
            elseif (strpos($path, 'gestion') !== false) $modulo = 'Gestiones Académicas';
            elseif (strpos($path, 'carrera') !== false) $modulo = 'Carreras';
            elseif (strpos($path, 'administrativo') !== false) $modulo = 'Administrativos';

            $descripcion = "Operación $accion en endpoint: /$path";
            
            // Intentar extraer datos descriptivos del request
            $nombres = [];
            if ($request->input('nombre')) $nombres[] = "Nombre: " . $request->input('nombre');
            if ($request->input('nombres')) $nombres[] = "Nombres: " . $request->input('nombres');
            if ($request->input('carnet')) $nombres[] = "CI: " . $request->input('carnet');
            if ($request->input('email')) $nombres[] = "Email: " . $request->input('email');
            if ($request->input('sigla')) $nombres[] = "Sigla: " . $request->input('sigla');
            if ($request->input('codigo')) $nombres[] = "Código: " . $request->input('codigo');
            
            $detalleInfo = count($nombres) > 0 ? " [" . implode(', ', $nombres) . "]" : "";

            // Lógica para descripciones más humanas
            if (preg_match('/postulantes\/(\d+)\/pagar/', $path, $matches)) {
                $descripcion = "Registró el pago del postulante (ID: " . $matches[1] . ")";
            } elseif (preg_match('/requisitos\/([\d\-]+)\/estado/', $path, $matches)) {
                $descripcion = "Actualizó el estado de un requisito asignado";
            } elseif (preg_match('/reportes\/generar/', $path)) {
                $descripcion = "Generó un nuevo reporte en PDF/Excel";
            } elseif (preg_match('/materias\/(\d+)\/requisitos/', $path, $matches)) {
                $descripcion = "Gestionó el catálogo de requisitos de la materia (ID: " . $matches[1] . ")";
            } elseif (preg_match('/gestiones-academicas\/(\d+)\/grupos\/generar/', $path, $matches)) {
                $descripcion = "Generó y guardó los grupos para la gestión académica (ID: " . $matches[1] . ")";
            } elseif (preg_match('/gestiones-academicas\/(\d+)\/horarios\/generar/', $path, $matches)) {
                $descripcion = "Generó y guardó los horarios automáticos para la gestión (ID: " . $matches[1] . ")";
            } elseif (preg_match('/gestiones-academicas\/(\d+)\/admision\/asignar/', $path, $matches)) {
                $descripcion = "Calculó la admisión y asignó carreras a los postulantes para la gestión (ID: " . $matches[1] . ")";
            } elseif (preg_match('/gestiones-academicas\/(\d+)/', $path, $matches) && $accion !== 'Crear') {
                $descripcion = ($accion === 'Actualizar' ? "Actualizó" : "Eliminó") . " la gestión académica (ID: " . $matches[1] . ")" . $detalleInfo;
            } elseif (preg_match('/usuarios\/(\d+)\/toggle-status/', $path, $matches)) {
                $descripcion = "Cambió el estado (Activó/Desactivó) del usuario (ID: " . $matches[1] . ")";
            } elseif (preg_match('/([a-zA-Z\-]+)\/(\d+)/', $path, $matches) && $accion !== 'Crear') {
                $entidad = str_replace('-', ' ', $matches[1]);
                $descripcion = ($accion === 'Actualizar' ? "Actualizó" : "Eliminó") . " un registro en $entidad (ID: " . $matches[2] . ")" . $detalleInfo;
            } else {
                // Fallback genérico más bonito
                $entidad = strtolower($modulo);
                if ($accion === 'Crear') $descripcion = "Creó un nuevo registro$detalleInfo en el módulo de $entidad";
                if ($accion === 'Actualizar') $descripcion = "Actualizó un registro$detalleInfo en el módulo de $entidad";
                if ($accion === 'Eliminar') $descripcion = "Eliminó un registro$detalleInfo en el módulo de $entidad";
            }

            DB::table('bitacora')->insert([
                'id_usuario' => $user->id,
                'accion' => $accion,
                'modulo' => $modulo,
                'descripcion' => $descripcion,
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
