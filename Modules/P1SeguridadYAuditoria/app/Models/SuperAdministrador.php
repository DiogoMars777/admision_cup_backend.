<?php

namespace Modules\P1SeguridadYAuditoria\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuperAdministrador extends Model
{
    use HasFactory;

    protected $table = 'super_administrador';

    protected $fillable = [
        'id_persona', 'cargo', 'estado'
    ];
}
