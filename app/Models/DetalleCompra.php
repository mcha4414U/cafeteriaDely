<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleCompra extends Model
{
    protected $table = 'detalle_compras';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:3', 'costo_unitario' => 'decimal:2', 'subtotal' => 'decimal:2'];
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class)->withTrashed();
    }

    public function compra()
    {
        return $this->belongsTo(Compra::class);
    }
}
