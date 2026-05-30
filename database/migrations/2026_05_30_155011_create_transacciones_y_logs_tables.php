<?php
// Evaluaciones, admisiones, asistencias y auditoría del sistema 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // 23. EVALUACION 
        Schema::create('evaluacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_materia')->constrained('materia')->onDelete('cascade');
            $table->foreignId('id_gestionacademica')->constrained('gestion_academica')->onDelete('cascade');
            $table->string('nombre_eva', 100);
            $table->decimal('puntaje_max', 5, 2);
            $table->date('fecha')->nullable();
            $table->timestamps();
        });

        // 24. NOTA 
        Schema::create('nota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_postulante')->constrained('persona')->onDelete('cascade');
            $table->foreignId('id_evaluacion')->constrained('evaluacion')->onDelete('cascade');
            $table->decimal('puntaje_obtenido', 5, 2);
            $table->string('estado', 20)->nullable();
            $table->timestamps();
        });

        // 25. ASISTENCIA 
        Schema::create('asistencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_postulante')->constrained('persona')->onDelete('cascade');
            $table->foreignId('id_docente')->constrained('persona');
            $table->foreignId('id_horario')->constrained('horario')->onDelete('cascade');
            $table->date('fecha');
            $table->string('observacion', 255)->nullable();
            $table->string('estado', 20);
            $table->timestamps();
        });

        // 26. CARGAMASIVA 
        Schema::create('cargamasiva', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('usuario')->onDelete('cascade');
            $table->string('nombre_archivo', 150);
            $table->string('tipo_archivo', 50);
            $table->date('fecha_carga');
            $table->integer('cant_registro');
            $table->integer('registro_correcto');
            $table->integer('registro_error');
            $table->timestamps();
        });

        // 27. ADMISION 
        Schema::create('admision', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_postulante')->constrained('persona')->onDelete('cascade');
            $table->foreignId('id_gestionacademica')->constrained('gestion_academica')->onDelete('cascade');
            $table->foreignId('id_carrera')->constrained('carrera')->onDelete('cascade');
            $table->decimal('promedio_fin', 5, 2)->nullable();
            $table->string('estado', 20);
            $table->string('observación', 255)->nullable(); // Conservando el acento exacto de tu Excel 
            $table->timestamps();
        });

        // 28. BITACORA 
        Schema::create('bitacora', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('usuario')->onDelete('cascade');
            $table->string('accion', 100);
            $table->string('modulo', 100);
            $table->text('descripcion')->nullable();
            $table->date('fecha')->useCurrent();
            $table->time('hora')->useCurrent();
            $table->string('ip_usuario', 45)->nullable();
            $table->timestamps();
        });

        // 29. REPORTE 
        Schema::create('reporte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('usuario')->onDelete('cascade');
            $table->string('tipo', 100);
            $table->date('fecha');
            $table->string('filtro_aplicado', 255)->nullable();
            $table->string('formato', 20);
            $table->text('contenido')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('reporte');
        Schema::dropIfExists('bitacora');
        Schema::dropIfExists('admision');
        Schema::dropIfExists('cargamasiva');
        Schema::dropIfExists('asistencia');
        Schema::dropIfExists('nota');
        Schema::dropIfExists('evaluacion');
    }
};