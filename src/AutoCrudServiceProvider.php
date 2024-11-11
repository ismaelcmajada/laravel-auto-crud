<?php

namespace Ismaelcmajada\LaravelAutoCrud;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class AutoCrudServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Cargar las rutas del paquete
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Registrar el middleware
        $this->registerMiddleware();

        $this->publishes([
            // Vue Components
            __DIR__ . '/../resources/js/Components' => resource_path('js/Components'),

            // JavaScript Utilities
            __DIR__ . '/../resources/js/Utils' => resource_path('js/Utils'),

            // Composables
            __DIR__ . '/../resources/js/Composables' => resource_path('js/Composables'),
        ], 'laravel-auto-crud');
    }

    public function register()
    {
        //
    }

    protected function registerMiddleware()
    {
        // Registrar el alias del middleware
        $router = $this->app['router'];
        $router->aliasMiddleware('checkForbiddenActions', \Ismaelcmajada\LaravelAutoCrud\Http\Middleware\CheckForbiddenActions::class);
    }
}
