<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    protected $table = 'ventas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['fecha' => 'datetime', 'anulada_at' => 'datetime', 'subtotal' => 'decimal:2', 'descuento' => 'decimal:2', 'total' => 'decimal:2', 'recibido' => 'decimal:2', 'cambio' => 'decimal:2'];
    }

    public function detalles()
    {
        return $this->hasMany(DetalleVenta::class);
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }
}
