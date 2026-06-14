<?php

namespace Modules\P2PostulantesYRequisitos\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Requisito extends Model
{
    use HasFactory;

    protected $table = 'requisito';

    protected $fillable = [
        'id_abministrador', 'nombre', 'descripcion', 'tipo_requisito', 'estado'
    ];
}
