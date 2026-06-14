<?php

namespace Modules\P4OfertaAcademica\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MateriaRequisito extends Model
{
    use HasFactory;

    protected $table = 'materia_requisito';

    protected $fillable = [
        'id_materia', 'id_requisito', 'obligatorio', 'estado'
    ];
}
