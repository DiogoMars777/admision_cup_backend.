<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BitacoraController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('bitacora')
            ->join('usuario', 'bitacora.id_usuario', '=', 'usuario.id')
            ->join('persona', 'usuario.id_persona', '=', 'persona.id')
            ->join('rol', 'usuario.id_rol', '=', 'rol.id')
            ->select(
                'bitacora.id',
                'persona.nombre as usuario',
                'rol.nombre as rol',
                'bitacora.accion',
                'bitacora.modulo',
                'bitacora.descripcion',
                'bitacora.fecha',
                'bitacora.hora',
                'bitacora.ip_usuario as ip'
            );

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where('persona.nombre', 'ilike', "%{$search}%")
                  ->orWhere('bitacora.descripcion', 'ilike', "%{$search}%");
        }

        if ($request->has('accion') && $request->accion != '') {
            $query->where('bitacora.accion', $request->accion);
        }

        if ($request->has('fecha') && $request->fecha != '') {
            $query->where('bitacora.fecha', $request->fecha);
        }

        return response()->json($query->orderBy('bitacora.id', 'desc')->get());
    }

    public function stats()
    {
        $totalMes = DB::table('bitacora')
            ->whereMonth('fecha', now()->month)
            ->whereYear('fecha', now()->year)
            ->count();

        $hoy = DB::table('bitacora')
            ->where('fecha', now()->toDateString())
            ->count();

        $usuariosActivos = DB::table('usuario')
            ->where('estado', 'Activo')
            ->count();

        return response()->json([
            'total_mes' => $totalMes,
            'hoy' => $hoy,
            'usuarios_activos' => $usuariosActivos
        ]);
    }
}
