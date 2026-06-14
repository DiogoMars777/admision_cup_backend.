<?php

namespace Modules\P8Reportes\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\PostulantesImport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\DB;

class CargaMasivaController extends Controller
{
    public function uploadPostulantes(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:5120',
        ]);

        $file = $request->file('file');

        DB::beginTransaction();
        try {
            $import = new PostulantesImport();
            Excel::import($import, $file);

            $resultados = $import->getResultados();
            $cantErrores = count($resultados['errores']);
            $cantTotal = $resultados['exitos'] + $cantErrores;
            
            // Registrar en tabla cargamasiva
            DB::table('cargamasiva')->insert([
                'id_usuario' => $request->user() ? $request->user()->id : 1, // Fallback a 1 si no hay usuario en sesión
                'nombre_archivo' => $file->getClientOriginalName(),
                'tipo_archivo' => $file->getClientOriginalExtension(),
                'fecha_carga' => now()->toDateString(),
                'cant_registro' => $cantTotal,
                'registro_correcto' => $resultados['exitos'],
                'registro_error' => $cantErrores,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            if ($resultados['exitos'] > 0) {
                return response()->json([
                    'message' => "Se procesaron {$resultados['exitos']} registros de postulantes exitosamente.",
                    'details' => $resultados['errores']
                ], 200);
            } else {
                return response()->json([
                    'message' => 'No se pudo registrar a ningún postulante.',
                    'details' => $resultados['errores']
                ], 422);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error crítico al procesar el archivo Excel',
                'details' => [$e->getMessage()]
            ], 500);
        }
    }

    public function uploadNotas(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:5120',
        ]);

        $file = $request->file('file');

        DB::beginTransaction();
        try {
            $gestionActiva = DB::table('gestion_academica')->where('estado', 'Activo')->first();
            if (!$gestionActiva) {
                return response()->json([
                    'message' => 'No hay una gestión académica activa. No se pueden cargar notas.',
                    'details' => []
                ], 400);
            }

            $import = new \App\Imports\NotasImport($gestionActiva->id);
            Excel::import($import, $file);

            $resultados = $import->getResultados();
            $cantErrores = count($resultados['errores']);
            $cantTotal = $resultados['exitos'] + $cantErrores;
            
            DB::table('cargamasiva')->insert([
                'id_usuario' => $request->user() ? $request->user()->id : 1,
                'nombre_archivo' => $file->getClientOriginalName(),
                'tipo_archivo' => $file->getClientOriginalExtension(),
                'fecha_carga' => now()->toDateString(),
                'cant_registro' => $cantTotal,
                'registro_correcto' => $resultados['exitos'],
                'registro_error' => $cantErrores,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            if ($resultados['exitos'] > 0) {
                return response()->json([
                    'message' => "Se registraron {$resultados['exitos']} notas exitosamente.",
                    'details' => $resultados['errores']
                ], 200);
            } else {
                return response()->json([
                    'message' => 'No se pudo registrar ninguna nota nueva.',
                    'details' => $resultados['errores']
                ], 422);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error crítico al procesar el archivo Excel de Notas',
                'details' => [$e->getMessage()]
            ], 500);
        }
    }

    public function downloadPlantillaPostulantes()
    {
        return response()->streamDownload(function () {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Cabeceras
            $sheet->setCellValue('A1', 'carnet');
            $sheet->setCellValue('B1', 'nombre');
            $sheet->setCellValue('C1', 'sexo');
            $sheet->setCellValue('D1', 'telefono');
            $sheet->setCellValue('E1', 'correo');

            // Fila de ejemplo
            $sheet->setCellValue('A2', '12345678');
            $sheet->setCellValue('B2', 'Juan Perez Gomez');
            $sheet->setCellValue('C2', 'Masculino');
            $sheet->setCellValue('D2', '71234567');
            $sheet->setCellValue('E2', 'juan@ejemplo.com');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 'Plantilla_Postulantes.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ]);
    }

    public function downloadPlantillaNotas()
    {
        return response()->streamDownload(function () {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            $sheet->setCellValue('A1', 'carnet');
            $sheet->setCellValue('B1', 'materia_nombre');
            $sheet->setCellValue('C1', 'evaluacion_id');
            $sheet->setCellValue('D1', 'puntaje');

            // Ejemplos de las 4 materias sin acentos para coincidir con la DB
            $sheet->setCellValue('A2', '10000000');
            $sheet->setCellValue('B2', 'Matematica');
            $sheet->setCellValue('C2', '1');
            $sheet->setCellValue('D2', '85.5');

            $sheet->setCellValue('A3', '10000000');
            $sheet->setCellValue('B3', 'Computacion');
            $sheet->setCellValue('C3', '1');
            $sheet->setCellValue('D3', '90.0');

            $sheet->setCellValue('A4', '10000001');
            $sheet->setCellValue('B4', 'Fisica');
            $sheet->setCellValue('C4', '2');
            $sheet->setCellValue('D4', '45.0');

            $sheet->setCellValue('A5', '10000001');
            $sheet->setCellValue('B5', 'Ingles');
            $sheet->setCellValue('C5', '2');
            $sheet->setCellValue('D5', '60.5');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 'Plantilla_Notas.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ]);
    }
}
