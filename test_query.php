<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$ctrl = app()->make('Modules\P7EvaluacionesYAdmision\Http\Controllers\GestionAcademicaController');
$res = $ctrl->getPostulantesPorGrupo(1);
echo 'SUCCESS: ' . strlen($res->getContent());
