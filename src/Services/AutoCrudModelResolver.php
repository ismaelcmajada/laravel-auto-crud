<?php

namespace Ismaelcmajada\LaravelAutoCrud\Services;

use Illuminate\Support\Str;

class AutoCrudModelResolver
{
    public function className($model)
    {
        return 'App\\Models\\' . Str::studly($model);
    }

    public function resolve($model)
    {
        $modelClass = $this->className($model);

        if (!class_exists($modelClass)) {
            $apiPrefix = trim(config('laravel-auto-crud.api.prefix', 'api/laravel-auto-crud'), '/');

            if (request()->expectsJson() || request()->is($apiPrefix) || request()->is($apiPrefix . '/*')) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Model not found',
                ], 404));
            }

            abort(404, 'Model not found');
        }

        return new $modelClass;
    }
}
