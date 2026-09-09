<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proveedor extends Model
{
    use SoftDeletes;

    protected $table = 'proveedores';

    protected $fillable = ['nombre', 'empresa', 'telefono', 'direccion', 'observaciones', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }
}
