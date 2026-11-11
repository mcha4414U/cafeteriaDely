<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WhatsAppWebhookController extends Controller
{
    public function verificar(Request $r)
    {
        abort_unless(config('services.whatsapp.enabled', false), 503);
        $secreto = (string) config('services.whatsapp.webhook_verify_token');
        abort_unless($secreto !== '' && $r->query('hub_mode') === 'subscribe' && hash_equals($secreto, (string) $r->query('hub_verify_token')), 403);

        return response((string) $r->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
    }

    public function recibir(Request $r)
    {
        abort_unless(config('services.whatsapp.enabled', false), 503);
        $secreto = (string) config('services.whatsapp.app_secret');
        abort_if($secreto === '', 503);
        abort_unless(hash_equals('sha256='.hash_hmac('sha256', $r->getContent(), $secreto), (string) $r->header('X-Hub-Signature-256')), 403);
        abort_if(strlen($r->getContent()) > 1048576, 413);
        $datos = $r->json()->all();
        if (($datos['object'] ?? null) !== 'whatsapp_business_account') {
            return response()->json(['ok' => true]);
        }
        foreach (($datos['entry'] ?? []) as $entrada) {
            foreach (($entrada['changes'] ?? []) as $c) {
                $valor = $c['value'] ?? [];
                if ((string) ($valor['metadata']['phone_number_id'] ?? '') !== (string) config('services.whatsapp.phone_number_id')) {
                    continue;
                }
                foreach (($valor['statuses'] ?? []) as $estado) {
                    $nombre = $estado['status'] ?? '';
                    $id = $estado['id'] ?? null;
                    $fecha = $estado['timestamp'] ?? null;
                    if (! is_string($id) || ! is_numeric($fecha) || ! in_array($nombre, ['sent', 'delivered', 'read', 'failed'])) {
                        continue;
                    }
                    DB::transaction(function () use ($estado, $nombre, $id, $fecha) {
                        $a = DB::table('alertas_stock')->where('mensaje_meta_id', $id)->lockForUpdate()->first();
                        if (! $a || (int) $a->meta_evento_at > (int) $fecha) {
                            return;
                        }
                        $rangos = ['aceptado' => 0, 'enviado' => 1, 'entregado' => 2, 'leido' => 3, 'error' => 0];
                        $mapa = ['sent' => 'enviado', 'delivered' => 'entregado', 'read' => 'leido', 'failed' => 'error'];
                        $nuevo = $mapa[$nombre];
                        if (($rangos[$a->envio_estado] ?? 0) > ($rangos[$nuevo] ?? 0)) {
                            return;
                        }
                        $codigo = $estado['errors'][0]['code'] ?? null;
                        DB::table('alertas_stock')->where('id', $a->id)->update(['envio_estado' => $nuevo, 'meta_evento_at' => (int) $fecha,
                            'error_envio' => $nombre === 'failed' ? 'Meta no entregó el aviso. Código: '.(is_numeric($codigo) ? (int) $codigo : 'no disponible').'.' : null, 'updated_at' => now()]);
                    });
                }
            }
        }

        return response()->json(['ok' => true]);
    }
}
