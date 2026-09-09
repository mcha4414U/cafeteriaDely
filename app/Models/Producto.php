<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use SoftDeletes;

    protected $table = 'productos';

    protected $fillable = ['nombre', 'descripcion', 'categoria_id', 'codigo', 'precio_compra', 'precio_venta', 'stock_minimo', 'unidad_medida', 'imagen', 'fecha_vencimiento', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean', 'precio_compra' => 'decimal:2', 'precio_venta' => 'decimal:2', 'stock_actual' => 'decimal:3', 'stock_minimo' => 'decimal:3', 'fecha_vencimiento' => 'date'];
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class)->withTrashed();
    }

    public function estadoStock(): string
    {
        return $this->stock_actual <= 0 ? 'Agotado' : ($this->stock_actual <= $this->stock_minimo ? 'Stock bajo' : 'Normal');
    }
}
