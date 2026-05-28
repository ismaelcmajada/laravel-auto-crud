<?php

namespace Ismaelcmajada\LaravelAutoCrud\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Ismaelcmajada\LaravelAutoCrud\Services\AutoCrudModelResolver;

class CheckForbiddenActions
{
    protected $models;

    public function __construct(AutoCrudModelResolver $models)
    {
        $this->models = $models;
    }

    public function handle($request, Closure $next)
    {
        if ($request->route('model')) {
            $user = Auth::user();
            $userRole = $user->laravel_auto_crud_role;
            $model = $request->route('model');
            $modelClass = $this->models->className($model);

            if (class_exists($modelClass)) {
                $forbiddenActions = $userRole ? $modelClass::getForbiddenActions()[$userRole] ?? [] : [];
                $customForbiddenActions = method_exists($modelClass, 'getCustomForbiddenActions')
                    ? $modelClass::getCustomForbiddenActions()
                    : [];
                $action = explode('@', $request->route()->getActionName())[1];
                $forbidden = false;

                if (in_array('all', $forbiddenActions)) {
                    $forbidden = true;
                } elseif (in_array($action, $forbiddenActions)) {
                    $forbidden = true;
                } elseif (isset($forbiddenActions['custom'])) {
                    foreach ($forbiddenActions['custom'] as $customAction) {
                        if (isset($customForbiddenActions[$customAction])) {
                            $forbidden = $customForbiddenActions[$customAction]($user, $action, $request);
                        }
                    }
                }

                if ($forbidden) {
                    $apiPrefix = trim(config('laravel-auto-crud.api.prefix', 'api/laravel-auto-crud'), '/');

                    if ($request->expectsJson() || $request->is($apiPrefix) || $request->is($apiPrefix . '/*')) {
                        return response()->json([
                            'success' => false,
                            'message' => 'No tienes permiso para realizar esta acción.',
                        ], 403);
                    }

                    return Redirect::back()->withErrors(['error' => 'No tienes permiso para realizar esta acción.']);
                }
            } else {
                $apiPrefix = trim(config('laravel-auto-crud.api.prefix', 'api/laravel-auto-crud'), '/');

                if ($request->expectsJson() || $request->is($apiPrefix) || $request->is($apiPrefix . '/*')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El modelo especificado no existe.',
                    ], 404);
                }

                abort(404, "El modelo especificado no existe.");
            }
        }

        return $next($request);
    }
}
