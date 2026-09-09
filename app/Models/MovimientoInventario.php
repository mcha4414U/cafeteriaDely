<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoInventario extends Model
{
    protected $table = 'movimientos_inventario';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['fecha' => 'datetime', 'cantidad' => 'decimal:3', 'stock_anterior' => 'decimal:3', 'stock_nuevo' => 'decimal:3'];
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class)->withTrashed();
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function compra()
    {
        return $this->belongsTo(Compra::class);
    }

    public function etiquetaTipo(): string
    {
        return match ($this->tipo) {
            'entrada_compra' => 'Entrada por compra','salida_venta' => 'Salida por venta','salida_desperdicio' => 'Salida por desperdicio','ajuste' => 'Ajuste manual',default => $this->tipo
        };
    }
}
