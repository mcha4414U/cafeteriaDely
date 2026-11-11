<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('alertas:actualizar', function () { app(\App\Services\ServicioAlertas::class)->actualizarTodas(); $this->info('Alertas revisadas.'); });
Schedule::command('alertas:actualizar')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('queue:work database --queue=whatsapp --stop-when-empty --tries=1 --max-time=50')->everyMinute()->withoutOverlapping()->when(fn () => (bool) config('services.whatsapp.enabled', false));
