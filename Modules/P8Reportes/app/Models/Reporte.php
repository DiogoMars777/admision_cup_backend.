<?php

namespace Modules\P8Reportes\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reporte extends Model
{
    use HasFactory;

    protected $table = 'reporte';

    protected $fillable = [
        'id_usuario', 'tipo', 'fecha', 'filtro_aplicado', 'formato', 'contenido'
    ];
}
