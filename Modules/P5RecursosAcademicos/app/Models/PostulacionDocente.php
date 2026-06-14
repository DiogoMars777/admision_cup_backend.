<?php

namespace Modules\P5RecursosAcademicos\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostulacionDocente extends Model
{
    use HasFactory;

    protected $table = 'postulacion_docente';

    protected $fillable = [
        'id_aspirante_docente', 'id_materia', 'fecha_postulacion', 'estado', 'observacion'
    ];
}
