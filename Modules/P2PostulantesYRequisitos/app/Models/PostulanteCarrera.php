<?php

namespace Modules\P2PostulantesYRequisitos\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostulanteCarrera extends Model
{
    use HasFactory;

    protected $table = 'postulante_carrera';

    protected $fillable = [
        'id_postulante', 'id_carrera', 'prioridad', 'id_modalidad'
    ];
}
