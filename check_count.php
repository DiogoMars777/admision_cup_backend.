<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$count = \Modules\P2PostulantesYRequisitos\Models\Postulante::count();
echo "Postulantes count: " . $count . "\n";
