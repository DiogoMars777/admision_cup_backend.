<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;

Route::post('/login', [AuthController::class, 'login']);

// Recuperación de contraseña (público)
Route::post('/forgot-password', [PasswordResetController::class, 'sendCode']);
Route::post('/verify-code', [PasswordResetController::class, 'verifyCode']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);

use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\PostulanteController;
use App\Http\Controllers\RequisitoController;
use App\Http\Controllers\MateriaController;
use App\Http\Controllers\DocenteController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\AulaController;
use App\Http\Controllers\RolController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
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

    // Requisitos (Catálogo y Enlaces)
    Route::get('/catalogo-requisitos', [RequisitoController::class, 'getCatalogo']);
    Route::post('/catalogo-requisitos', [RequisitoController::class, 'storeCatalogo']);
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

    Route::post('/logout', [AuthController::class, 'logout']);
});
