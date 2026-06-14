<?php

namespace Modules\P4OfertaAcademica\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModalidadCarrera extends Model
{
    use HasFactory;

    protected $table = 'modalidad_carrera';

    protected $fillable = [
        'id_modalidad', 'id_carrera'
    ];
}
