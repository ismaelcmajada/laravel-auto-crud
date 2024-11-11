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
            __DIR__ . '/../config/laravel-auto-crud.php' => config_path('laravel-auto-crud.php'),
        ], 'laravel-auto-crud-config');

        $this->publishes([
            // Vue Components
            __DIR__ . '/../resources/js/Components/LaravelAutoCrud' => resource_path('js/Components/LaravelAutoCrud'),

            // JavaScript Utilities
            __DIR__ . '/../resources/js/Utils/LaravelAutoCrud' => resource_path('js/Utils/LaravelAutoCrud'),

            // Composables
            __DIR__ . '/../resources/js/Composables/LaravelAutoCrud' => resource_path('js/Composables/LaravelAutoCrud'),
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
