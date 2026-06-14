<?php

namespace Modules\P6PlanificacionAcademica\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GestionAcademica extends Model
{
    use HasFactory;

    protected $table = 'gestion_academica';

    protected $fillable = [
        'id_postulante', 'nombre', 'año', 'fecha_ini', 'fecha_fin', 'estado', 'id_gestion_cup'
    ];
}
