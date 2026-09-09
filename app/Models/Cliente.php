<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use SoftDeletes;

    protected $table = 'clientes';

    protected $fillable = ['nombre', 'telefono', 'direccion', 'observaciones', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean', 'es_generico' => 'boolean'];
    }
}
