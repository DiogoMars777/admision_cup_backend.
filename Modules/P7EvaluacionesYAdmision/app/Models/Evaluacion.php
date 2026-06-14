<?php

namespace Modules\P7EvaluacionesYAdmision\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluacion extends Model
{
    use HasFactory;

    protected $table = 'evaluacion';

    protected $fillable = [
        'id_materia', 'id_gestionacademica', 'nombre_eva', 'puntaje_max', 'fecha'
    ];
}
