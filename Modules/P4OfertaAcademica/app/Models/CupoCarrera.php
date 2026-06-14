<?php

namespace Modules\P4OfertaAcademica\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CupoCarrera extends Model
{
    use HasFactory;

    protected $table = 'cupo_carrera';

    protected $fillable = [
        'id_carrera', 'id_gestionacademica', 'cupo_max', 'cupo_disp'
    ];
}
