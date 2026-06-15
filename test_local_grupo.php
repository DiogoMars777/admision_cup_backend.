<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

DB::table('grupo')->insert([
    'id' => 1,
    'id_gestionacademica' => 1,
    'nombre' => 'Test Grupo',
    'cupo_max' => 10,
    'cant_estudiante' => 1,
    'modalidad' => 'Virtual',
    'turno' => 'Tarde',
    'estado' => 'Activo'
]);

DB::table('postulante_grupo')->insert([
    'id_postulante' => 2, // Assuming persona id 2 is a postulante
    'id_grupo' => 1,
    'fecha_asignacion' => now()
]);

$ctrl = app()->make('Modules\P7EvaluacionesYAdmision\Http\Controllers\GestionAcademicaController');
try {
    $res = $ctrl->getPostulantesPorGrupo(1);
    echo $res->getContent();
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
}
