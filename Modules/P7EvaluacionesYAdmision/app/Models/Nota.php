<?php

namespace Modules\P7EvaluacionesYAdmision\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nota extends Model
{
    use HasFactory;

    protected $table = 'nota';

    protected $fillable = [
        'id_postulante', 'id_evaluacion', 'puntaje_obtenido', 'estado'
    ];
}
