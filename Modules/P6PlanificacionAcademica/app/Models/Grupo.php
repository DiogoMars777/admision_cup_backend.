<?php

namespace Modules\P6PlanificacionAcademica\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    use HasFactory;

    protected $table = 'grupo';

    protected $fillable = [
        'id_gestionacademica', 'nombre', 'cupo_max', 'cant_estudiante', 'modalidad', 'turno', 'estado'
    ];
}
