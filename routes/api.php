<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\P1SeguridadYAuditoria\Http\Controllers\AuthController;
use Modules\P1SeguridadYAuditoria\Http\Controllers\PasswordResetController;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');

// Recuperación de contraseña (público)
Route::post('/forgot-password', [PasswordResetController::class, 'sendCode']);
Route::post('/verify-code', [PasswordResetController::class, 'verifyCode']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);

// Verificar bloqueo por intentos fallidos (público)
Route::post('/check-lockout', [AuthController::class, 'checkLockoutStatus']);

// Catálogos públicos
Route::get('/public/carreras', function() {
    return response()->json(\Illuminate\Support\Facades\DB::table('carrera')->select('id', 'nombre')->where('estado', 'Activo')->get());
});

Route::get('/public/gestion-activa', function() {
    $gestion = \Modules\P6PlanificacionAcademica\Models\GestionAcademica::query()
        ->leftJoin('gestion_cup', 'gestion_academica.id_gestion_cup', '=', 'gestion_cup.id')
        ->select('gestion_academica.año', 'gestion_cup.nombre as cup_nombre')
        ->where('gestion_academica.estado', 'Activo')
        ->first();
        
    if ($gestion) {
        return response()->json(['cup' => $gestion->cup_nombre . ' - ' . $gestion->año]);
    }
    return response()->json(['cup' => 'CUP ' . date('Y')]);
});


use Modules\P1SeguridadYAuditoria\Http\Controllers\UsuarioController;
use Modules\P1SeguridadYAuditoria\Http\Controllers\BitacoraController;
use Modules\P2PostulantesYRequisitos\Http\Controllers\PostulanteController;
use Modules\P3GestionDePagos\Http\Controllers\PagoController;
use Modules\P2PostulantesYRequisitos\Http\Controllers\RequisitoController;
use Modules\P4OfertaAcademica\Http\Controllers\MateriaController;
use Modules\P5RecursosAcademicos\Http\Controllers\AspiranteDocenteController;
use Modules\P5RecursosAcademicos\Http\Controllers\DocenteController;
use Modules\P6PlanificacionAcademica\Http\Controllers\GrupoController;
use Modules\P6PlanificacionAcademica\Http\Controllers\GrupoGeneradorController;
use Modules\P6PlanificacionAcademica\Http\Controllers\HorarioGeneradorController;
use Modules\P5RecursosAcademicos\Http\Controllers\AulaController;
use Modules\P1SeguridadYAuditoria\Http\Controllers\RolController;
use Modules\P4OfertaAcademica\Http\Controllers\CarreraController;
use Modules\P7EvaluacionesYAdmision\Http\Controllers\GestionAcademicaController;
use Modules\P5RecursosAcademicos\Http\Controllers\DocenteAsignadorController;
use Modules\P8Reportes\Http\Controllers\ReportesController;
use Modules\P2PostulantesYRequisitos\Http\Controllers\PostulantePortalController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // Reportes IA
    Route::post('/reportes/generar', [ReportesController::class, 'generar']);
    
    // P1 Seguridad y Acceso
    Route::get('/usuarios', [UsuarioController::class, 'index']);
    Route::post('/usuarios', [UsuarioController::class, 'store']);
    Route::put('/usuarios/{id}', [UsuarioController::class, 'update']);
    Route::patch('/usuarios/{id}/toggle-status', [UsuarioController::class, 'toggleStatus']);
    Route::delete('/usuarios/{id}', [UsuarioController::class, 'destroy']);
    
    // Roles
    Route::get('/roles', [RolController::class, 'index']);
    Route::post('/roles', [RolController::class, 'store']);
    Route::put('/roles/{id}', [RolController::class, 'update']);
    Route::delete('/roles/{id}', [RolController::class, 'destroy']);

    // Bitácora
    Route::get('/bitacora', [BitacoraController::class, 'index']);
    Route::get('/bitacora/stats', [BitacoraController::class, 'stats']);

    // P2 Postulantes
    Route::get('/postulantes', [PostulanteController::class, 'index']);
    Route::post('/postulantes', [PostulanteController::class, 'store']);
    Route::put('/postulantes/{id}', [PostulanteController::class, 'update']);
    Route::delete('/postulantes/{id}', [PostulanteController::class, 'destroy']);
    Route::post('/postulantes/{id}/pagar', [PagoController::class, 'pagar']);
    Route::get('/postulantes-pago', [PagoController::class, 'getPendientesPago']);

    // Requisitos (Catálogo y Enlaces)
    Route::get('/catalogo-requisitos', [RequisitoController::class, 'getCatalogo']);
    Route::post('/catalogo-requisitos', [RequisitoController::class, 'storeCatalogo']);
    Route::put('/catalogo-requisitos/{id}', [RequisitoController::class, 'updateCatalogo']);
    Route::delete('/catalogo-requisitos/{id}', [RequisitoController::class, 'deleteCatalogo']);
    
    Route::get('/requisitos', [RequisitoController::class, 'index']);
    Route::post('/requisitos', [RequisitoController::class, 'store']);
    Route::delete('/requisitos/{id}', [RequisitoController::class, 'destroy']);
    Route::patch('/requisitos/{id}/estado', [RequisitoController::class, 'updateEstado']);

    // P3 Materias
    Route::get('/materias', [MateriaController::class, 'index']);
    Route::post('/materias', [MateriaController::class, 'store']);
    Route::put('/materias/{id}', [MateriaController::class, 'update']);
    Route::delete('/materias/{id}', [MateriaController::class, 'destroy']);
    Route::get('/materias/{materiaId}/requisitos', [RequisitoController::class, 'getMateriaRequisitos']);
    Route::post('/materias/{materiaId}/requisitos', [RequisitoController::class, 'syncMateriaRequisitos']);

    // P2 Aspirantes Docentes
    Route::get('/aspirantes-docentes', [AspiranteDocenteController::class, 'index']);
    Route::post('/aspirantes-docentes', [AspiranteDocenteController::class, 'createAspirante']);
    Route::get('/aspirantes-docentes/{id}/materias', [AspiranteDocenteController::class, 'getMateriasPostuladas']);
    Route::put('/aspirantes-docentes/{id}', [AspiranteDocenteController::class, 'updateAspirante']);
    Route::delete('/aspirantes-docentes/{id}', [AspiranteDocenteController::class, 'deleteAspirante']);
    Route::get('/aspirantes-docentes/{id}/materias/{idMateria}/requisitos', [AspiranteDocenteController::class, 'getRequisitosMateria']);
    Route::post('/aspirantes-docentes/requisito/toggle', [AspiranteDocenteController::class, 'toggleRequisito']);
    Route::post('/aspirantes-docentes/postular', [AspiranteDocenteController::class, 'postularMateria']);
    Route::post('/aspirantes-docentes/{id}/convertir', [AspiranteDocenteController::class, 'convertirADocente']);

    // P3 Docentes
    Route::get('/docentes', [DocenteController::class, 'index']);
    Route::post('/docentes', [DocenteController::class, 'store']);
    Route::put('/docentes/{id}', [DocenteController::class, 'update']);
    Route::delete('/docentes/{id}', [DocenteController::class, 'destroy']);

    // P3 Grupos
    Route::get('/grupos', [GrupoController::class, 'index']);
    Route::post('/grupos', [GrupoController::class, 'store']);
    Route::put('/grupos/{id}', [GrupoController::class, 'update']);
    Route::delete('/grupos/{id}', [GrupoController::class, 'destroy']);
    Route::get('/gestiones', [GrupoController::class, 'getGestiones']);

    // P3 Aulas
    Route::get('/aulas', [AulaController::class, 'index']);
    Route::post('/aulas', [AulaController::class, 'store']);
    Route::put('/aulas/{id}', [AulaController::class, 'update']);
    Route::delete('/aulas/{id}', [AulaController::class, 'destroy']);

    // P3 Carreras
    Route::get('/carreras', [CarreraController::class, 'index']);
    Route::post('/carreras', [CarreraController::class, 'store']);
    Route::put('/carreras/{id}', [CarreraController::class, 'update']);
    Route::delete('/carreras/{id}', [CarreraController::class, 'destroy']);

    // P3 Gestión Académica
    Route::get('/gestiones-academicas/cups', [GestionAcademicaController::class, 'getCups']);
    Route::get('/gestiones-academicas', [GestionAcademicaController::class, 'index']);
    Route::post('/gestiones-academicas', [GestionAcademicaController::class, 'store']);
    Route::put('/gestiones-academicas/{id}', [GestionAcademicaController::class, 'update']);
    Route::delete('/gestiones-academicas/{id}', [GestionAcademicaController::class, 'destroy']);
    Route::get('/gestiones-academicas/{id}/evaluaciones', [GestionAcademicaController::class, 'getEvaluaciones']);
    Route::put('/gestiones-academicas/{id}/evaluaciones', [GestionAcademicaController::class, 'updateEvaluacion']);
    Route::get('/gestiones-academicas/cups', [GestionAcademicaController::class, 'getCups']);
    
    // Rutas para ver postulantes por grupo en gestión académica
    Route::get('/gestiones-academicas/{id}/postulantes/grupos', [GestionAcademicaController::class, 'getGruposPostulantes']);
    Route::get('/gestiones-academicas/grupos/{grupoId}/postulantes', [GestionAcademicaController::class, 'getPostulantesPorGrupo']);

    // Rutas de Admisión y Resumen de Carreras
    Route::get('/gestiones-academicas/{id}/admision/resumen', [GestionAcademicaController::class, 'getResumenAdmision']);
    Route::post('/gestiones-academicas/{id}/admision/asignar', [GestionAcademicaController::class, 'asignarCarreras']);

    // Rutas de Generación de Grupos
    Route::get('/gestiones-academicas/{id}/grupos/resumen', [GrupoGeneradorController::class, 'getResumen']);
    Route::post('/gestiones-academicas/{id}/grupos/simular', [GrupoGeneradorController::class, 'simular']);
    Route::post('/gestiones-academicas/{id}/grupos/generar', [GrupoGeneradorController::class, 'generar']);

    // Rutas de Generación de Horarios
    Route::get('/gestiones-academicas/{id}/horarios/resumen', [HorarioGeneradorController::class, 'getResumen']);
    Route::post('/gestiones-academicas/{id}/horarios/simular', [HorarioGeneradorController::class, 'simular']);
    Route::post('/gestiones-academicas/{id}/horarios/generar', [HorarioGeneradorController::class, 'generar']);

    // Rutas de Asignación de Docentes
    Route::get('/gestiones-academicas/{id}/asignaciones-docentes/resumen', [DocenteAsignadorController::class, 'getResumen']);
    Route::get('/gestiones-academicas/{id}/asignaciones-docentes/grupos', [DocenteAsignadorController::class, 'getGruposProgramados']);
    Route::get('/gestiones-academicas/asignaciones-docentes/grupos/{grupoId}/materias', [DocenteAsignadorController::class, 'getMateriasDeGrupo']);
    Route::get('/gestiones-academicas/asignaciones-docentes/materias/{materiaId}/docentes', [DocenteAsignadorController::class, 'getDocentesHabilitados']);
    Route::post('/gestiones-academicas/{id}/grupo-materia/{grupoMateriaId}/asignar-docente', [DocenteAsignadorController::class, 'asignarDocente']);
    Route::delete('/gestiones-academicas/{id}/grupo-materia/{grupoMateriaId}/quitar-docente', [DocenteAsignadorController::class, 'quitarDocente']);
    Route::post('/gestiones-academicas/{id}/asignaciones-docentes/automatica', [DocenteAsignadorController::class, 'asignacionAutomatica']);

    // Portal Docente
    Route::get('/docente-portal/dashboard', [\Modules\P5RecursosAcademicos\Http\Controllers\DocentePortalController::class, 'getDashboardData']);
    Route::get('/docente-portal/grupos/{id}/estudiantes', [\Modules\P5RecursosAcademicos\Http\Controllers\DocentePortalController::class, 'getEstudiantesPorGrupo']);
    Route::post('/docente-portal/grupos/{id}/notas', [\Modules\P5RecursosAcademicos\Http\Controllers\DocentePortalController::class, 'guardarNotas']);
    Route::get('/docente-portal/materias', [\Modules\P5RecursosAcademicos\Http\Controllers\DocentePortalController::class, 'getMateriasHabilitadas']);

    // Portal Postulante
    Route::get('/postulante-portal/mi-grupo', [PostulantePortalController::class, 'getMiGrupo']);
    
    // Carga Masiva
    Route::post('/carga-masiva/postulantes', [\Modules\P8Reportes\Http\Controllers\CargaMasivaController::class, 'uploadPostulantes']);
    Route::get('/carga-masiva/plantilla-postulantes', [\Modules\P8Reportes\Http\Controllers\CargaMasivaController::class, 'downloadPlantillaPostulantes']);
    Route::post('/carga-masiva/notas', [\Modules\P8Reportes\Http\Controllers\CargaMasivaController::class, 'uploadNotas']);
    Route::get('/carga-masiva/plantilla-notas', [\Modules\P8Reportes\Http\Controllers\CargaMasivaController::class, 'downloadPlantillaNotas']);

    // Portal Docente - Asistencia
    Route::get('/docente-portal/grupos/{id}/asistencias', [\Modules\P5RecursosAcademicos\Http\Controllers\DocenteAsistenciaController::class, 'getHistorial']);
    Route::post('/docente-portal/grupos/{id}/asistencias', [\Modules\P5RecursosAcademicos\Http\Controllers\DocenteAsistenciaController::class, 'store']);
    Route::get('/docente-portal/asistencias/{id}', [\Modules\P5RecursosAcademicos\Http\Controllers\DocenteAsistenciaController::class, 'show']);
    Route::put('/docente-portal/asistencias/{id}', [\Modules\P5RecursosAcademicos\Http\Controllers\DocenteAsistenciaController::class, 'update']);
    Route::delete('/docente-portal/asistencias/{id}', [\Modules\P5RecursosAcademicos\Http\Controllers\DocenteAsistenciaController::class, 'destroy']);

    Route::post('/logout', [AuthController::class, 'logout']);
});
