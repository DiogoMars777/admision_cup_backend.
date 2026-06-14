<?php

namespace Modules\P4OfertaAcademica\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Materia extends Model
{
    use HasFactory;

    protected $table = 'materia';

    protected $fillable = [
        'nombre', 'descripcion', 'estado'
    ];
}
