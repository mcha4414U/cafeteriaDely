<?php

namespace App\Http\Controllers;

use App\Services\ServicioWhatsApp;

class WhatsAppController extends Controller
{
    public const MENSAJE_PRUEBA = 'Cafetería Dely: stock bajo.';

    public function enviarPrueba(ServicioWhatsApp $servicio)
    {
        $resultado = $servicio->enviarMensaje((string) config('services.whatsapp.destinatario'), self::MENSAJE_PRUEBA);

        return $resultado['exito']
            ? redirect()->route('productos.index')->with('estado', 'Meta aceptó el aviso de stock bajo. La entrega al teléfono todavía no está confirmada.')
            : redirect()->route('productos.index')->withErrors(['whatsapp' => 'No se pudo enviar el aviso de stock bajo. '.$resultado['detalle']]);
    }
}
