<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class RespaldarBase extends Command
{
    protected $signature = 'dely:respaldar';

    protected $description = 'Crea un respaldo privado de la base actual sin mostrar credenciales';

    public function handle(): int
    {
        $conexion = DB::connection();
        $config = $conexion->getConfig();
        if (! in_array($config['driver'], ['mysql', 'mariadb'])) {
            $this->error('Este comando requiere MySQL o MariaDB.');

            return self::FAILURE;
        }
        $bin = is_file('C:/xampp/mysql/bin/mysqldump.exe') ? 'C:/xampp/mysql/bin/mysqldump.exe' : (new ExecutableFinder)->find('mysqldump');
        if (! $bin) {
            $this->error('No se encontró mysqldump.');

            return self::FAILURE;
        }
        $carpeta = storage_path('app/private/respaldos');
        if (! is_dir($carpeta)) {
            mkdir($carpeta, 0700, true);
        }
        $ruta = $carpeta.'/dely-'.now()->format('Ymd-His').'-'.Str::random(6).'.sql';
        $temporal = tempnam(sys_get_temp_dir(), 'dely-db-');
        try {
            $escapar = fn ($v) => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $v).'"';
            $contenido = "[client]\n";
            foreach (['host', 'port', 'username' => 'user', 'password'] as $k => $v) {
                $clave = is_int($k) ? $v : $k;
                $opcion = is_int($k) ? $v : $v;
                $contenido .= $opcion.'='.$escapar($config[$clave] ?? '')."\n";
            }
            file_put_contents($temporal, $contenido);
            @chmod($temporal, 0600);
            $proceso = new Process([$bin, '--defaults-extra-file='.$temporal, '--single-transaction', '--quick', '--skip-lock-tables', '--routines', '--triggers', '--hex-blob', '--result-file='.$ruta, $conexion->getDatabaseName()]);
            $proceso->setTimeout(120);
            $proceso->run();
            if (! $proceso->isSuccessful() || ! is_file($ruta) || filesize($ruta) === 0) {
                $this->error('El respaldo no se completó. No se realizarán cambios con este respaldo.');

                return self::FAILURE;
            }
            $this->info('Respaldo creado: '.$ruta);

            return self::SUCCESS;
        } finally {
            if (is_file($temporal)) {
                unlink($temporal);
            }
        }
    }
}
