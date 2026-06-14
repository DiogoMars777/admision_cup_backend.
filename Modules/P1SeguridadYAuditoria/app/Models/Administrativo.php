<?php

namespace Modules\P1SeguridadYAuditoria\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Administrativo extends Model
{
    use HasFactory;

    protected $table = 'administrativo';

    protected $fillable = [
        'id_persona', 'area', 'cargo', 'estado', 'permisos'
    ];

    protected $casts = [
        'permisos' => 'array',
    ];
}
