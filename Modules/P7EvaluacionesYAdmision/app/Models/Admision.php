<?php

namespace Modules\P7EvaluacionesYAdmision\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Admision extends Model
{
    use HasFactory;

    protected $table = 'admision';

    protected $fillable = [
        'id_postulante', 'id_gestionacademica', 'id_carrera', 'promedio_fin', 'estado', 'observación'
    ];
}
