<?php

namespace App\Jobs;

use App\Services\ServicioWhatsApp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class EnviarAlertaStock implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 40;

    public function __construct(public int $alertaId) {}

    public function handle(ServicioWhatsApp $whatsapp): void
    {
        if (! config('services.whatsapp.enabled', false)) {
            return;
        }
        // Reclamación atómica. Un timeout no produce un segundo envío automático.
        $tomada = DB::table('alertas_stock')->where('id', $this->alertaId)->where('estado', 'activa')->where('envio_estado', 'pendiente')
            ->update(['envio_estado' => 'procesando', 'intento_at' => now(), 'updated_at' => now()]);
        if (! $tomada) {
            return;
        }
        $alerta = DB::table('alertas_stock')->find($this->alertaId);
        $r = $whatsapp->enviarMensaje((string) config('services.whatsapp.destinatario'), $alerta->mensaje);
        DB::table('alertas_stock')->where('id', $this->alertaId)->update([
            'envio_estado' => $r['exito'] ? 'aceptado' : 'error', 'enviada' => $r['exito'],
            'fecha_envio' => $r['exito'] ? now() : null, 'mensaje_meta_id' => $r['id'] ?? null,
            'error_envio' => $r['exito'] ? null : ($r['detalle'] ?? 'No se pudo confirmar el envío.'), 'updated_at' => now()]);
    }

    public function failed(?\Throwable $error): void
    {
        DB::table('alertas_stock')->where('id', $this->alertaId)->where('envio_estado', 'procesando')
            ->update(['envio_estado' => 'incierto', 'error_envio' => 'No se confirmó el envío. Revisa WhatsApp antes de reintentar.', 'updated_at' => now()]);
    }
}
