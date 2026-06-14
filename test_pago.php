<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$postulanteId = \App\Models\P2_GestionDePostulantes\Postulante::whereNotIn('id_persona', \App\Models\P2_GestionDePostulantes\Pago::pluck('id_postulante'))->first()->id_persona;
$ctrl = $app->make('App\Http\Controllers\P2_GestionDePostulantes\CU2_RegistrarPostulante\PostulanteController');
echo json_encode($ctrl->pagar($postulanteId)->getData());
