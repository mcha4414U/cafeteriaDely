<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use SoftDeletes;

    protected $table = 'productos';

    protected $fillable = ['requiere_receta', 'contenido_por_unidad', 'presentacion', 'nombre', 'descripcion', 'categoria_id', 'codigo', 'tipo', 'precio_compra', 'precio_venta', 'stock_actual', 'stock_minimo', 'unidad_medida', 'imagen', 'fecha_vencimiento', 'estado'];

    public function requiereReceta(): bool
    {
        if ($this->tipo === 'insumo') return false;
        // Una receta existente nunca se omite por un indicador antiguo/inconsistente.
        return (bool) $this->requiere_receta || $this->receta !== null;
    }

    public function etiquetaTipo(): string
    {
        return ['venta' => 'Producto', 'insumo' => 'Insumo', 'ambos' => 'Producto e insumo (existente)'][$this->tipo] ?? $this->tipo;
    }

    public function tienePresentacion(): bool
    {
        return $this->tipo === 'insumo' && $this->contenido_por_unidad !== null && bccomp($this->contenido_por_unidad, '0', 3) > 0;
    }

    public function unidadesEquivalentes(): ?string
    {
        return $this->tienePresentacion() ? bcdiv($this->stock_actual, $this->contenido_por_unidad, 6) : null;
    }

    public function envasesPara(string $cantidad): ?string
    {
        if (! $this->tienePresentacion()) {
            return null;
        }
        $enteros = bcdiv($cantidad, $this->contenido_por_unidad, 0);
        return bccomp(bcmul($enteros, $this->contenido_por_unidad, 3), $cantidad, 3) < 0 ? bcadd($enteros, '1', 0) : $enteros;
    }

    public function receta()
    {
        return $this->hasOne(Receta::class);
    }

    public function usadoComoInsumo()
    {
        return $this->hasMany(DetalleReceta::class, 'insumo_id');
    }

    protected function casts(): array
    {
        return ['requiere_receta' => 'boolean', 'precio_compra_presentacion' => 'decimal:2', 'contenido_por_unidad' => 'decimal:3', 'estado' => 'boolean', 'precio_compra' => 'decimal:2', 'precio_venta' => 'decimal:2', 'stock_actual' => 'decimal:3', 'stock_minimo' => 'decimal:3', 'fecha_vencimiento' => 'date'];
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class)->withTrashed();
    }

    public function estadoStock(): string
    {
        return $this->stock_actual <= 0 ? 'Agotado' : ($this->stock_actual <= $this->stock_minimo ? 'Stock bajo' : 'Normal');
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoInventario::class);
    }
}
