<?php
// Mapea la conformación de grupos, horarios y requisitos 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // 18. GRUPO 
        Schema::create('grupo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_gestionacademica')->constrained('gestion_academica')->onDelete('cascade');
            $table->string('nombre', 50);
            $table->integer('cupo_max');
            $table->integer('cant_estudiante')->default(0);
            $table->string('modalidad', 50)->nullable();
            $table->string('turno', 50)->nullable();
            $table->string('estado', 20)->default('Activo');
            $table->timestamps();
        });

        // 19. POSTULANTE_GRUPO 
        Schema::create('postulante_grupo', function (Blueprint $table) {
            $table->foreignId('id_postulante')->constrained('persona')->onDelete('cascade');
            $table->foreignId('id_grupo')->constrained('grupo')->onDelete('cascade');
            $table->date('fecha_asignacion');
            $table->primary(['id_postulante', 'id_grupo']);
            $table->timestamps();
        });

        // 20. HORARIO 
        Schema::create('horario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_grupo')->constrained('grupo')->onDelete('cascade');
            $table->foreignId('id_docente')->constrained('persona'); // Apunta a persona (Rol docente)
            $table->foreignId('id_materia')->constrained('materia');
            $table->foreignId('id_aula')->constrained('aula');
            $table->string('dia', 20);
            $table->time('hora_ini');
            $table->time('hora_fin');
            $table->string('modalidad', 50)->nullable();
            $table->timestamps();
        });

        // 21. REQUISITO 
        Schema::create('requisito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_abministrador')->constrained('persona'); // Siguiendo el nombre exacto de tu Excel 
            $table->foreignId('id_postulante')->constrained('persona')->onDelete('cascade');
            $table->string('nombre', 100);
            $table->string('descripcion', 255)->nullable();
            $table->string('estado', 20)->default('Pendiente');
            $table->timestamps();
        });

        // 22. PAGO 
        Schema::create('pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_postulante')->constrained('persona')->onDelete('cascade');
            $table->foreignId('id_comprobante')->constrained('comprobante');
            $table->decimal('monto', 10, 2);
            $table->string('metodo_pago', 50)->nullable();
            $table->string('codigo_transaccion', 100)->nullable();
            $table->string('estado', 20)->default('Procesado');
            $table->date('fecha');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('pago');
        Schema::dropIfExists('requisito');
        Schema::dropIfExists('horario');
        Schema::dropIfExists('postulante_grupo');
        Schema::dropIfExists('grupo');
    }
};