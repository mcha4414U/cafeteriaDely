<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesPermisosSeeder::class);
        // Catálogos mínimos; no crear cuentas con contraseñas predeterminadas.
        foreach (['Efectivo', 'QR', 'Transferencia', 'Tarjeta'] as $nombre) {
            DB::table('metodos_pago')->updateOrInsert(['nombre' => $nombre], [
                'estado' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('clientes')->updateOrInsert(['es_generico' => true], [
            'nombre' => 'Cliente genérico', 'estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('configuraciones')->updateOrInsert(['clave' => 'nombre_negocio'], [
            'valor' => 'Cafetería Dely', 'grupo' => 'cafeteria',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
