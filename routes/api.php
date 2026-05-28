<?php

use Illuminate\Support\Facades\Route;
use Ismaelcmajada\LaravelAutoCrud\Http\Controllers\ApiAutoCrudController;
use Ismaelcmajada\LaravelAutoCrud\Http\Controllers\ApiCustomFieldDefinitionController;
use Ismaelcmajada\LaravelAutoCrud\Http\Controllers\FileController;
use Ismaelcmajada\LaravelAutoCrud\Http\Controllers\ImageController;

$apiPrefix = trim(config('laravel-auto-crud.api.prefix', 'api/laravel-auto-crud'), '/');
$apiMiddleware = config('laravel-auto-crud.api.middleware', ['forceJsonResponse', 'api', 'auth:sanctum', 'checkForbiddenActions']);
$publicMiddleware = config('laravel-auto-crud.api.public_middleware');

if ($publicMiddleware === null) {
    $publicMiddleware = array_values(array_filter($apiMiddleware, function ($middleware) {
        return !in_array($middleware, ['auth', 'auth:sanctum', 'checkForbiddenActions'], true);
    }));
}

Route::middleware($publicMiddleware)
    ->prefix($apiPrefix)
    ->group(function () {
        Route::get('/public/images/{model}/{field}/{id}', [ImageController::class, 'publicImage']);
        Route::get('/public/files/{model}/{field}/{id}', [FileController::class, 'publicFile']);
    });

Route::middleware($apiMiddleware)
    ->prefix($apiPrefix)
    ->group(function () {
        Route::get('/private/images/{model}/{field}/{id}', [ImageController::class, 'privateImage']);
        Route::get('/private/files/{model}/{field}/{id}', [FileController::class, 'privateFile']);

        Route::get('/custom-fields-types', [ApiCustomFieldDefinitionController::class, 'getAvailableTypes'])->name('laravel-auto-crud.api.custom-fields.types');
        Route::prefix('custom-fields/{model}')->group(function () {
            Route::get('/', [ApiCustomFieldDefinitionController::class, 'index'])->name('laravel-auto-crud.api.custom-fields.index');
            Route::post('/', [ApiCustomFieldDefinitionController::class, 'store'])->name('laravel-auto-crud.api.custom-fields.store');
            Route::put('/{id}', [ApiCustomFieldDefinitionController::class, 'update'])->name('laravel-auto-crud.api.custom-fields.update');
            Route::patch('/{id}', [ApiCustomFieldDefinitionController::class, 'update']);
            Route::post('/{id}', [ApiCustomFieldDefinitionController::class, 'update']);
            Route::delete('/{id}', [ApiCustomFieldDefinitionController::class, 'destroy'])->name('laravel-auto-crud.api.custom-fields.destroy');
            Route::post('/{id}/destroy', [ApiCustomFieldDefinitionController::class, 'destroy']);
            Route::post('/reorder', [ApiCustomFieldDefinitionController::class, 'reorder'])->name('laravel-auto-crud.api.custom-fields.reorder');
        });

        Route::get('/{model}/schema', [ApiAutoCrudController::class, 'schema'])->name('laravel-auto-crud.api.model.schema');
        Route::post('/{model}/load-calendar-events', [ApiAutoCrudController::class, 'loadCalendarEvents'])->name('laravel-auto-crud.api.model.load-calendar-events');
        Route::post('/{model}/load-autocomplete-items', [ApiAutoCrudController::class, 'loadAutocompleteItems'])->name('laravel-auto-crud.api.model.load-autocomplete-items');
        Route::post('/{model}/load-items', [ApiAutoCrudController::class, 'loadItems'])->name('laravel-auto-crud.api.model.load-items');
        Route::get('/{model}/export-excel', [ApiAutoCrudController::class, 'exportExcel'])->name('laravel-auto-crud.api.model.exportExcel');
        Route::get('/{model}/all', [ApiAutoCrudController::class, 'getAll'])->name('laravel-auto-crud.api.model.all');
        Route::get('/{model}/{id}', [ApiAutoCrudController::class, 'getItem'])->name('laravel-auto-crud.api.model.getItem');

        Route::post('/{model}', [ApiAutoCrudController::class, 'store'])->name('laravel-auto-crud.api.model.store');
        Route::match(['put', 'patch', 'post'], '/{model}/{id}', [ApiAutoCrudController::class, 'update'])->name('laravel-auto-crud.api.model.update');
        Route::delete('/{model}/{id}', [ApiAutoCrudController::class, 'destroy'])->name('laravel-auto-crud.api.model.destroy');
        Route::post('/{model}/{id}/destroy', [ApiAutoCrudController::class, 'destroy']);
        Route::delete('/{model}/{id}/force', [ApiAutoCrudController::class, 'destroyPermanent'])->name('laravel-auto-crud.api.model.destroyPermanent');
        Route::post('/{model}/{id}/permanent', [ApiAutoCrudController::class, 'destroyPermanent']);
        Route::post('/{model}/{id}/restore', [ApiAutoCrudController::class, 'restore'])->name('laravel-auto-crud.api.model.restore');

        Route::post('/{model}/{id}/bind/{externalRelation}/{item}', [ApiAutoCrudController::class, 'bind'])->name('laravel-auto-crud.api.model.bind');
        Route::match(['put', 'patch', 'post'], '/{model}/{id}/pivot/{externalRelation}/{item}', [ApiAutoCrudController::class, 'updatePivot'])->name('laravel-auto-crud.api.model.updatePivot');
        Route::delete('/{model}/{id}/unbind/{externalRelation}/{item}', [ApiAutoCrudController::class, 'unbind'])->name('laravel-auto-crud.api.model.unbind');
        Route::post('/{model}/{id}/unbind/{externalRelation}/{item}', [ApiAutoCrudController::class, 'unbind']);
    });
