<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfiguracionController extends Controller
{
    public function index()
    {
        $nombre = DB::table('configuraciones')->where('clave', 'nombre_negocio')->value('valor') ?? 'Cafetería Dely';
        $metodos = DB::table('metodos_pago')->get();
        $whatsapp = [];
        foreach (['token', 'phone_number_id', 'business_account_id', 'destinatario'] as $k) {
            $whatsapp[$k] = filled(config('services.whatsapp.'.$k));
        }

        return view('configuracion.indice', compact('nombre', 'metodos', 'whatsapp'));
    }

    public function guardar(Request $r)
    {
        $d = $r->validate(['nombre' => 'required|string|max:100', 'metodos' => 'required|array|min:1', 'metodos.*' => 'required|integer|distinct|exists:metodos_pago,id']);
        DB::transaction(function () use ($d) {
            DB::table('configuraciones')->updateOrInsert(['clave' => 'nombre_negocio'], ['valor' => $d['nombre'], 'grupo' => 'cafeteria', 'updated_at' => now()]);
            DB::table('metodos_pago')->update(['estado' => false, 'updated_at' => now()]);
            DB::table('metodos_pago')->whereIn('id', $d['metodos'])->update(['estado' => true, 'updated_at' => now()]);
        });

        return back()->with('estado', 'Configuración actualizada.');
    }
}
