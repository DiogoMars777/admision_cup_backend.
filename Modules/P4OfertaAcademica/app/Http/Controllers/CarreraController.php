<?php

namespace Modules\P4OfertaAcademica\Http\Controllers;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\P4OfertaAcademica\Models\Carrera;
use Modules\P4OfertaAcademica\Models\CupoCarrera;
use Modules\P6PlanificacionAcademica\Models\GestionAcademica;
use Modules\P4OfertaAcademica\Models\ModalidadCarrera;
use Modules\P2PostulantesYRequisitos\Models\PostulanteCarrera;

class CarreraController extends Controller
{
    public function index(Request $request)
    {
        $gestionActiva = GestionAcademica::where('estado', 'Activo')->first();
        $idGestion = $gestionActiva ? $gestionActiva->id : 0;

        $query = Carrera::query()
            ->leftJoin('cupo_carrera', function ($join) use ($idGestion) {
                $join->on('carrera.id', '=', 'cupo_carrera.id_carrera')
                     ->where('cupo_carrera.id_gestionacademica', '=', $idGestion);
            })
            ->select('carrera.*', 'cupo_carrera.cupo_max', 'cupo_carrera.cupo_disp');

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where('carrera.nombre', 'ilike', "%{$search}%");
        }

        $carreras = $query->orderBy('carrera.id', 'desc')->get();
        return response()->json($carreras);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:150|unique:carrera,nombre',
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:20',
            'cupo_max' => 'required|integer|min:1'
        ]);

        DB::beginTransaction();
        try {
            $carrera = Carrera::create([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'estado' => $request->estado ?? 'Activo',
            ]);
            $idCarrera = $carrera->id;

            $gestionActiva = GestionAcademica::where('estado', 'Activo')->first();
            if ($gestionActiva) {
                CupoCarrera::create([
                    'id_carrera' => $idCarrera,
                    'id_gestionacademica' => $gestionActiva->id,
                    'cupo_max' => $request->cupo_max,
                    'cupo_disp' => $request->cupo_max,
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Carrera y cupo creados exitosamente'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear la carrera'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string|max:150|unique:carrera,nombre,' . $id,
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:20',
            'cupo_max' => 'required|integer|min:1'
        ]);

        DB::beginTransaction();
        try {
            Carrera::where('id', $id)->update([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'estado' => $request->estado ?? 'Activo',
            ]);

            $gestionActiva = GestionAcademica::where('estado', 'Activo')->first();
            if ($gestionActiva) {
                $cupo = CupoCarrera::query()
                    ->where('id_carrera', $id)
                    ->where('id_gestionacademica', $gestionActiva->id)
                    ->first();

                if ($cupo) {
                    $diff = $request->cupo_max - $cupo->cupo_max;
                    $nuevoDisp = $cupo->cupo_disp + $diff;
                    // Asegurar que cupo_disp no sea menor a 0
                    if ($nuevoDisp < 0) $nuevoDisp = 0;

                    CupoCarrera::query()
                        ->where('id', $cupo->id)
                        ->update([
                            'cupo_max' => $request->cupo_max,
                            'cupo_disp' => $nuevoDisp,
                        ]);
                } else {
                    CupoCarrera::create([
                        'id_carrera' => $id,
                        'id_gestionacademica' => $gestionActiva->id,
                        'cupo_max' => $request->cupo_max,
                        'cupo_disp' => $request->cupo_max,
                    ]);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Carrera y cupo actualizados exitosamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar la carrera'], 500);
        }
    }

    public function destroy($id)
    {
        // Verificar si la carrera está siendo usada (por ejemplo, en modalidad_carrera o postulante_carrera)
        $enUso = ModalidadCarrera::where('id_carrera', $id)->exists();
        $enUsoPostulante = PostulanteCarrera::where('id_carrera', $id)->exists();

        if ($enUso || $enUsoPostulante) {
            return response()->json(['message' => 'No se puede eliminar la carrera porque tiene registros asociados'], 400);
        }

        Carrera::where('id', $id)->delete();
        return response()->json(['message' => 'Carrera eliminada exitosamente']);
    }
}
