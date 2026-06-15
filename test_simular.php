<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$ctrl = app()->make('Modules\P6PlanificacionAcademica\Http\Controllers\GrupoGeneradorController');
$res = $ctrl->simular(1);
echo $res->getContent();
