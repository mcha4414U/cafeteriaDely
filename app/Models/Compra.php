<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Compra extends Model
{
    protected $table = 'compras';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['fecha' => 'datetime', 'subtotal' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class)->withTrashed();
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function detalles()
    {
        return $this->hasMany(DetalleCompra::class);
    }
}
