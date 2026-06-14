<?php 
require 'vendor/autoload.php'; 
$app = require_once 'bootstrap/app.php'; 
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class); 
$kernel->bootstrap(); 

use Illuminate\Support\Facades\DB;

$ids = DB::table('postulante')->where('id_gestionacademica', 1)->pluck('id_persona'); 
$affected = DB::table('usuario')->whereIn('id_persona', $ids)->where('id_rol', 5)->update(['estado' => 'Inactivo']); 
echo 'Affected: ' . $affected;
