<?php

namespace Modules\P2PostulantesYRequisitos\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostulanteGrupo extends Model
{
    use HasFactory;

    protected $table = 'postulante_grupo';

    protected $fillable = [
        'id_postulante', 'id_grupo', 'fecha_asignacion'
    ];
}
