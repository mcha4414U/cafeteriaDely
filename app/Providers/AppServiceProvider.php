<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Pagination\Paginator::useBootstrapFive();
        \Illuminate\Support\Facades\View::composer('partials.notificaciones-stock', function ($view) {
            $avisosStock = auth()->user()?->puedeConsultarAvisosStock()
                ? app(\App\Services\ServicioAlertas::class)->operativas()->get()
                : collect();
            $view->with('avisosStock', $avisosStock);
        });
        \Illuminate\Support\Facades\RateLimiter::for('avisos-stock', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by((string) $request->user()?->id)
                ->response(function (\Illuminate\Http\Request $request, array $headers) {
                    $mensaje = 'Has solicitado varios avisos. Espera un minuto antes de volver a intentarlo.';
                    return $request->expectsJson()
                        ? response()->json(['mensaje' => $mensaje], 429, $headers)
                        : back()->withErrors(['aviso' => $mensaje])->withHeaders($headers);
                });
        });
        //
    }
}
