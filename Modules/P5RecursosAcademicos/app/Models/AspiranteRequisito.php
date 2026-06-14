<?php

namespace Modules\P5RecursosAcademicos\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AspiranteRequisito extends Model
{
    use HasFactory;

    protected $table = 'aspirante_requisito';

    protected $fillable = [
        'id_aspirante', 'id_materia_requisito', 'cumplido', 'estado', 'documento_url'
    ];
}
