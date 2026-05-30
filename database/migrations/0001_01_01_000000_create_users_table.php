<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. PERSONA 
        Schema::create('persona', function (Blueprint $table) {
            $table->id();
            $table->string('ci', 20)->unique();
            $table->string('nombre', 150);
            $table->string('sexo', 10)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->timestamps();
        });

        // 2. ROL 
        Schema::create('rol', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();
        });

        // 3. CARRERA 
        Schema::create('carrera', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->string('estado', 20)->default('Activo');
            $table->timestamps();
        });

        // 4. MATERIA 
        Schema::create('materia', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->string('estado', 20)->default('Activo');
            $table->timestamps();
        });

        // 5. AULA 
        Schema::create('aula', function (Blueprint $table) {
            $table->id();
            $table->string('aula_nro', 20)->unique();
            $table->integer('capacidad');
            $table->string('tipo_aula', 50)->nullable();
            $table->timestamps();
        });

        // 6. COMPROBANTE 
        Schema::create('comprobante', function (Blueprint $table) {
            $table->id();
            $table->string('nro_comprobante', 50)->unique();
            $table->date('fecha_emision');
            $table->timestamps();
        });

        // 7. ESPECIALIDAD 
        Schema::create('especialidad', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->timestamps();
        });

        // 8. USUARIO (Adaptada con campos nativos de Laravel para auth y recuperación) 
        Schema::create('usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_persona')->constrained('persona')->onDelete('cascade');
            $table->foreignId('id_rol')->constrained('rol');
            $table->string('email', 100)->unique(); // Reemplaza 'EMAIL' para compatibilidad 
            $table->string('password', 255);        // Reemplaza 'PASSWORD' encriptado 
            $table->string('estado', 20)->default('Activo');
            $table->rememberToken();                // Requerido por Laravel
            $table->timestamps();
        });

        // TABLAS DEL SISTEMA DE LARAVEL (Obligatorias para Recuperación de Claves y Sesiones)
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index(); // Vinculará al ID de tu tabla usuario
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('usuario');
        Schema::dropIfExists('especialidad');
        Schema::dropIfExists('comprobante');
        Schema::dropIfExists('aula');
        Schema::dropIfExists('materia');
        Schema::dropIfExists('carrera');
        Schema::dropIfExists('rol');
        Schema::dropIfExists('persona');
    }
};