<?php

namespace Modules\P6PlanificacionAcademica\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GestionCup extends Model
{
    use HasFactory;

    protected $table = 'gestion_cup';

    protected $fillable = [
        'nombre'
    ];
}
