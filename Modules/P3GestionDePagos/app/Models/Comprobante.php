<?php

namespace Modules\P3GestionDePagos\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comprobante extends Model
{
    use HasFactory;

    protected $table = 'comprobante';

    protected $fillable = [
        'nro_comprobante', 'fecha_emision'
    ];
}
