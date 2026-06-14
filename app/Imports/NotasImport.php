<?php

namespace App\Imports;

use Illuminate\Support\Facades\DB;
use App\Models\Shared\Persona;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Exception;

class NotasImport implements ToModel, WithHeadingRow
{
    private $errores = [];
    private $exitos = 0;
    private $idGestion;

    public function __construct($idGestion)
    {
        $this->idGestion = $idGestion;
    }

    public function model(array $row)
    {
        try {
            if (!isset($row['carnet']) || !isset($row['materia_nombre']) || !isset($row['evaluacion_id']) || !isset($row['puntaje'])) {
                $this->errores[] = "Fila incompleta. Requiere carnet, materia_nombre, evaluacion_id y puntaje.";
                return null;
            }

            $ci = $row['carnet'];
            $nombreMateria = $row['materia_nombre'];
            $evaluacionId = $row['evaluacion_id'];
            $puntaje = $row['puntaje'];

            $persona = Persona::where('ci', $ci)->first();
            if (!$persona) {
                $this->errores[] = "Carnet {$ci} no encontrado.";
                return null;
            }

            $postulante = DB::table('postulante')->where('id_persona', $persona->id)->first();
            if (!$postulante) {
                $this->errores[] = "La persona con Carnet {$ci} no es un postulante.";
                return null;
            }

            // Validar que el usuario del postulante esté activo
            $usuario = DB::table('usuario')->where('id_persona', $persona->id)->first();
            if (!$usuario || $usuario->estado !== 'Activo') {
                $this->errores[] = "El usuario del postulante con Carnet {$ci} no está Activo.";
                return null;
            }

            // Validar que esté inscrito en la gestión activa
            $inscripcion = DB::table('postulante_grupo')
                ->join('grupo', 'grupo.id', '=', 'postulante_grupo.id_grupo')
                ->where('postulante_grupo.id_postulante', $persona->id)
                ->where('grupo.id_gestionacademica', $this->idGestion)
                ->first();

            if (!$inscripcion) {
                $this->errores[] = "El postulante con Carnet {$ci} no está inscrito en ningún grupo de la gestión activa.";
                return null;
            }

            // Buscar materia ignorando mayúsculas, acentos y 's' al final
            $materiasDb = DB::table('materia')->get();
            $materia = null;
            
            // Función simple para limpiar string
            $cleanStr = function($str) {
                $str = mb_strtolower(trim($str), 'UTF-8');
                $str = str_replace(
                    ['á', 'é', 'í', 'ó', 'ú', 'ä', 'ë', 'ï', 'ö', 'ü', 'â', 'ê', 'î', 'ô', 'û'],
                    ['a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u'],
                    $str
                );
                return rtrim($str, 's'); // Quita la 's' final por si escriben Matemáticas en vez de Matemática
            };

            $searchClean = $cleanStr($nombreMateria);

            foreach ($materiasDb as $mDb) {
                if ($cleanStr($mDb->nombre) === $searchClean) {
                    $materia = $mDb;
                    break;
                }
            }

            if (!$materia) {
                $this->errores[] = "Materia {$nombreMateria} no encontrada.";
                return null;
            }

            // Buscar programacion de esa evaluacion para la materia en la gestion activa
            $prog = DB::table('programacion_evaluacion')
                ->where('id_evaluacion', $evaluacionId)
                ->where('id_materia', $materia->id)
                ->where('id_gestionacademica', $this->idGestion)
                ->first();

            if (!$prog) {
                $this->errores[] = "Evaluación ID {$evaluacionId} no programada para {$nombreMateria}.";
                return null;
            }

            // Actualizar o Insertar Nota
            DB::table('nota')->updateOrInsert(
                [
                    'id_postulante' => $persona->id,
                    'id_programacion_evaluacion' => $prog->id,
                    'id_materia' => $materia->id
                ],
                [
                    'puntaje_obtenido' => $puntaje,
                    'updated_at' => now(),
                    // Solo en caso de insert, se asignará el created_at porque updateOrInsert maneja updated_at si existe
                ]
            );

            $this->exitos++;

        } catch (Exception $e) {
            $this->errores[] = "Error en el Carnet {$row['carnet']}: " . $e->getMessage();
        }

        return null;
    }

    public function getResultados()
    {
        return [
            'exitos' => $this->exitos,
            'errores' => $this->errores
        ];
    }
}
