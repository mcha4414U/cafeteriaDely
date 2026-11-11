<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleReceta extends Model
{
    protected $table = 'detalle_recetas';

    protected $fillable = ['receta_id', 'insumo_id', 'cantidad', 'unidad_medida'];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:3'];
    }

    public function receta()
    {
        return $this->belongsTo(Receta::class);
    }

    public function insumo()
    {
        return $this->belongsTo(Producto::class, 'insumo_id')->withTrashed();
    }
}
