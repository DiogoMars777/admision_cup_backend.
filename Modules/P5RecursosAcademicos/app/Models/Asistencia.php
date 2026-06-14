<?php

namespace Modules\P5RecursosAcademicos\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asistencia extends Model
{
    use HasFactory;

    protected $table = 'asistencia';

    protected $fillable = [
        'id_postulante', 'id_docente', 'id_horario', 'fecha', 'observacion', 'estado'
    ];
}
