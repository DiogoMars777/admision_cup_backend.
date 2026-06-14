<?php

namespace Modules\P5RecursosAcademicos\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AspiranteDocente extends Model
{
    use HasFactory;

    protected $table = 'aspirante_docente';

    protected $fillable = [
        'id_persona', 'fecha_registro', 'grado_academico', 'experiencia', 'estado'
    ];
}
