<?php

namespace Modules\P2PostulantesYRequisitos\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Postulante extends Model
{
    use HasFactory;

    protected $table = 'postulante';

    protected $fillable = [
        'id_persona', 'fecha_nac', 'direccion', 'colegio', 'turno_preferido', 'modalidad_preferida'
    ];
}
