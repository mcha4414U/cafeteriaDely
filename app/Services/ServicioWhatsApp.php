<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ServicioWhatsApp
{
    public function enviarMensaje(string $destinatario, string $mensaje): array
    {
        if (! config('services.whatsapp.enabled', false)) {
            return $this->error('WhatsApp está desactivado por el momento. Las alertas internas siguen disponibles.');
        }
        $token = trim((string) config('services.whatsapp.token'));
        $telefonoId = trim((string) config('services.whatsapp.phone_number_id'));
        $version = (string) config('services.whatsapp.version', 'v23.0');
        $destinatario = preg_replace('/[\s()-]/', '', trim($destinatario));
        $destinatario = preg_replace('/^\+/', '', $destinatario);

        if ($token === '' || ! preg_match('/^\d+$/', $telefonoId) || ! preg_match('/^v\d+\.\d+$/', $version)) {
            Log::warning('WhatsApp: configuración incompleta o inválida.');

            return $this->error('Revisa la configuración de WhatsApp en el servidor.');
        }
        if (! preg_match('/^[1-9]\d{6,14}$/', $destinatario) || trim($mensaje) === '' || mb_strlen($mensaje) > 4096) {
            return $this->error('Revisa el destinatario con código de país y el texto del mensaje.');
        }

        try {
            // No reintentar automáticamente: un timeout puede ocurrir después de que Meta acepte el mensaje.
            $respuesta = Http::withToken($token)->acceptJson()->asJson()
                ->connectTimeout(5)->timeout(20)->withOptions(['allow_redirects' => false])
                ->post("https://graph.facebook.com/{$version}/{$telefonoId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $destinatario,
                    'type' => 'text',
                    'text' => ['body' => $mensaje],
                ]);
        } catch (ConnectionException $error) {
            // No registrar la excepción completa: puede contener cabeceras o datos de la solicitud.
            Log::error('WhatsApp: fallo de conexión o tiempo de espera agotado.', ['categoria' => 'conexion']);

            return $this->error('No se pudo confirmar el envío. Revisa WhatsApp antes de volver a intentarlo.');
        }

        if (! $respuesta->successful() || ! is_string($respuesta->json('messages.0.id')) || $respuesta->json('messages.0.id') === '') {
            $codigo = $respuesta->json('error.code');
            $subcodigo = $respuesta->json('error.error_subcode');
            // Lista limitada de datos técnicos; nunca cuerpo, token, destinatario ni mensaje de error externo.
            Log::error('WhatsApp: Meta rechazó el envío o devolvió una respuesta inesperada.', [
                'http_status' => $respuesta->status(),
                'codigo_meta' => is_numeric($codigo) ? (int) $codigo : null,
                'subcodigo_meta' => is_numeric($subcodigo) ? (int) $subcodigo : null,
            ]);

            if (is_numeric($codigo) && (int) $codigo === 190) {
                return $this->error(is_numeric($subcodigo) && (int) $subcodigo === 463
                    ? 'El token de WhatsApp ha vencido. Actualízalo en la configuración del servidor.'
                    : 'Meta rechazó el token de WhatsApp. Actualízalo en la configuración del servidor.');
            }

            return $this->error(is_numeric($codigo) && (int) $codigo === 131047
                ? 'El destinatario debe escribir primero al número de la API para abrir la ventana de 24 horas.'
                : 'Revisa la configuración de Meta y el registro técnico del servidor.');
        }

        return ['exito' => true, 'id' => $respuesta->json('messages.0.id'), 'mensaje' => 'Meta aceptó el mensaje. La entrega al teléfono todavía no está confirmada.'];
    }

    private function error(string $detalle): array
    {
        return ['exito' => false, 'detalle' => $detalle, 'mensaje' => 'No se pudo enviar el mensaje por WhatsApp. '.$detalle];
    }
}
