<?php

namespace Modules\P6PlanificacionAcademica\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Horario extends Model
{
    use HasFactory;

    protected $table = 'horario';

    protected $fillable = [
        'id_grupo', 'id_docente', 'id_materia', 'id_aula', 'dia', 'hora_ini', 'hora_fin', 'modalidad'
    ];
}
