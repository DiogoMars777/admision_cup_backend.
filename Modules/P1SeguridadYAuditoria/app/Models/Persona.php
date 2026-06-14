<?php

namespace Modules\P1SeguridadYAuditoria\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Persona extends Model
{
    use HasFactory;

    protected $table = 'persona';

    protected $fillable = [
        'ci', 'nombre', 'sexo', 'telefono'
    ];
}
