<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
class ComprobarWhatsApp extends Command {
    protected $signature='whatsapp:diagnostico';
    protected $description='Comprueba configuración y acceso a Meta sin enviar mensajes ni revelar secretos';
    public function handle():int {
        foreach(['token','phone_number_id','business_account_id','destinatario','webhook_verify_token','app_secret'] as $k)$this->line($k.': '.(filled(config('services.whatsapp.'.$k))?'CONFIGURADA':'AUSENTE'));
        $token=trim((string)config('services.whatsapp.token'));$id=trim((string)config('services.whatsapp.phone_number_id'));
        if($token===''||!ctype_digit($id)){$this->error('Configuración de acceso incompleta.');return self::FAILURE;}
        try {
            $r=Http::withToken($token)->acceptJson()->connectTimeout(5)->timeout(20)->withOptions(['allow_redirects'=>false])->get('https://graph.facebook.com/'.config('services.whatsapp.version','v23.0').'/'.$id,['fields'=>'id']);
            $this->line(json_encode(['http'=>$r->status(),'codigo_meta'=>is_numeric($r->json('error.code'))?(int)$r->json('error.code'):null,'subcodigo_meta'=>is_numeric($r->json('error.error_subcode'))?(int)$r->json('error.error_subcode'):null,'telefono_accesible'=>$r->successful()&&(string)$r->json('id')===$id],JSON_THROW_ON_ERROR));
            return $r->successful()?self::SUCCESS:self::FAILURE;
        }catch(\Throwable $e){$this->error('No se pudo conectar con Meta. No se enviaron mensajes.');return self::FAILURE;}
    }
}
