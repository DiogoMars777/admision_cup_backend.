<?php

namespace Modules\P3GestionDePagos\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    use HasFactory;

    protected $table = 'pago';

    protected $fillable = [
        'id_postulante', 'id_comprobante', 'monto', 'metodo_pago', 'codigo_transaccion', 'estado', 'fecha'
    ];
}
