<?php

namespace Ismaelcmajada\LaravelAutoCrud;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Ismaelcmajada\LaravelAutoCrud\Console\Commands\AiContextCommand;

class AutoCrudServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AiContextCommand::class,
            ]);
        }

        // Registrar el middleware
        $this->registerMiddleware();

        if (config('laravel-auto-crud.web.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }

        if (config('laravel-auto-crud.api.enabled', false)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }

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

            // Transport adapters (Inertia / API)
            __DIR__ . '/../resources/js/Adapters/LaravelAutoCrud' => resource_path('js/Adapters/LaravelAutoCrud'),
        ], 'laravel-auto-crud');

        // Publish migrations for custom fields
        $this->publishes([
            __DIR__ . '/../database/migrations/create_custom_fields_tables.php.stub' => database_path('migrations/' . date('Y_m_d_His') . '_create_custom_fields_tables.php'),
        ], 'laravel-auto-crud-migrations');

        // Publish the opencode / Claude-style agent skill so AI agents working
        // on the host project can discover and follow the package conventions.
        // Published into `.opencode/skills/laravel-auto-crud/SKILL.md` at the
        // project root by default, plus a copy under `.claude/skills/` for
        // tooling that expects the Claude location.
        $this->publishes([
            __DIR__ . '/../skills/laravel-auto-crud' => base_path('.opencode/skills/laravel-auto-crud'),
        ], 'laravel-auto-crud-skill');

        $this->publishes([
            __DIR__ . '/../skills/laravel-auto-crud' => base_path('.claude/skills/laravel-auto-crud'),
        ], 'laravel-auto-crud-skill-claude');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-auto-crud.php', 'laravel-auto-crud');
    }

    protected function registerMiddleware()
    {
        // Registrar el alias del middleware
        $router = $this->app['router'];
        $router->aliasMiddleware('checkForbiddenActions', \Ismaelcmajada\LaravelAutoCrud\Http\Middleware\CheckForbiddenActions::class);
        $router->aliasMiddleware('forceJsonResponse', \Ismaelcmajada\LaravelAutoCrud\Http\Middleware\ForceJsonResponse::class);
    }
}
