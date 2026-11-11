<?php

namespace Database\Seeders;

use App\Models\{Permiso, Rol};
use Illuminate\Database\Seeder;

class PreparacionesPermisosSeeder extends Seeder
{
    public function run(): void
    {
        $permiso = Permiso::firstOrCreate(['nombre' => 'preparaciones.gestionar']);
        // Administrador gestiona toda la cola; Cajero conserva sus preparaciones mediante pos.usar.
        $administrador = Rol::where('nombre', 'Administrador')->first();
        $administrador?->permisos()->syncWithoutDetaching([$permiso->id]);
    }
}
