<?php

namespace Modules\P4OfertaAcademica\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Carrera extends Model
{
    use HasFactory;

    protected $table = 'carrera';

    protected $fillable = [
        'nombre', 'descripcion', 'estado'
    ];
}
