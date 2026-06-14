<?php

namespace Modules\P5RecursosAcademicos\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Especialidad extends Model
{
    use HasFactory;

    protected $table = 'especialidad';

    protected $fillable = [
        'nombre'
    ];
}
