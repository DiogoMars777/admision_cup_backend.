<?php
// Mapea la especialización de roles (Herencia de Persona) 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // 9. POSTULANTE 
        Schema::create('postulante', function (Blueprint $table) {
            $table->foreignId('id_persona')->primary()->constrained('persona')->onDelete('cascade');
            $table->date('fecha_nac')->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('colegio', 150)->nullable();
            $table->timestamps();
        });

        // 10. DOCENTE 
        Schema::create('docente', function (Blueprint $table) {
            $table->foreignId('id_persona')->primary()->constrained('persona')->onDelete('cascade');
            $table->string('grado_academico', 100)->nullable();
            $table->integer('experiencia_docente')->nullable();
            $table->timestamps();
        });

        // 11. ADMINISTRATIVO 
        Schema::create('administrativo', function (Blueprint $table) {
            $table->foreignId('id_persona')->primary()->constrained('persona')->onDelete('cascade');
            $table->string('area', 100)->nullable();
            $table->string('cargo', 100)->nullable();
            $table->string('estado', 20)->default('Activo');
            $table->timestamps();
        });

        // 12. SUPER_ADMINISTRADOR 
        Schema::create('super_administrador', function (Blueprint $table) {
            $table->foreignId('id_persona')->primary()->constrained('persona')->onDelete('cascade');
            $table->string('cargo', 100)->nullable();
            $table->string('estado', 20)->default('Activo');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('super_administrador');
        Schema::dropIfExists('administrativo');
        Schema::dropIfExists('docente');
        Schema::dropIfExists('postulante');
    }
};