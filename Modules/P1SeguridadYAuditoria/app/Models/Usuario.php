<?php

namespace Modules\P1SeguridadYAuditoria\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'usuario';

    protected $fillable = [
        'id_persona', 'id_rol', 'email', 'password', 'estado', 'remember_token'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
}
