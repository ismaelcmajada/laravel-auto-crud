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
