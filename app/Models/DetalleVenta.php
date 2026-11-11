<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleVenta extends Model
{
    protected $table = 'detalle_ventas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:3', 'precio_unitario' => 'decimal:2', 'subtotal' => 'decimal:2'];
    }
}
