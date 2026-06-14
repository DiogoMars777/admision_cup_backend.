<?php

namespace Modules\P5RecursosAcademicos\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocenteRequisito extends Model
{
    use HasFactory;

    protected $table = 'docente_requisito';

    protected $fillable = [
        'id_docente', 'id_administrativo', 'id_requisito_materia', 'cumple', 'fecha_revision', 'observacion', 'estado'
    ];
}
