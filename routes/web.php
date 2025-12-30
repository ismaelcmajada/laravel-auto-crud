<?php

use Illuminate\Support\Facades\Route;
use Ismaelcmajada\LaravelAutoCrud\Http\Controllers\AutoCrudController;
use Ismaelcmajada\LaravelAutoCrud\Http\Controllers\AutoTableController;
use Ismaelcmajada\LaravelAutoCrud\Http\Controllers\AutoCompleteController;
use Ismaelcmajada\LaravelAutoCrud\Http\Controllers\SessionController;
use Ismaelcmajada\LaravelAutoCrud\Http\Controllers\ImageController;
use Ismaelcmajada\LaravelAutoCrud\Http\Controllers\FileController;
use Ismaelcmajada\LaravelAutoCrud\Http\Controllers\CalendarController;
use Ismaelcmajada\LaravelAutoCrud\Http\Controllers\CustomFieldDefinitionController;

Route::middleware('web')->group(function () {
    Route::get('/laravel-auto-crud/public/images/{model}/{field}/{id}', [ImageController::class, 'publicImage']);
    Route::get('/laravel-auto-crud/public/files/{model}/{field}/{id}', [FileController::class, 'publicFile']);

    Route::middleware(['auth', 'checkForbiddenActions'])->prefix('laravel-auto-crud')->group(function () {

        
        Route::get('/private/images/{model}/{field}/{id}', [ImageController::class, 'privateImage']);
        Route::get('/private/files/{model}/{field}/{id}', [FileController::class, 'privateFile']);

        Route::post('/session/setSession', [SessionController::class, 'setSession'])->name('laravel-auto-crud.session.setSession');

        // Custom Fields Management Routes - MUST be before {model} routes
        Route::get('/custom-fields-types', [CustomFieldDefinitionController::class, 'getAvailableTypes'])->name('laravel-auto-crud.custom-fields.types');
        Route::prefix('custom-fields/{model}')->group(function () {
            Route::get('/', [CustomFieldDefinitionController::class, 'index'])->name('laravel-auto-crud.custom-fields.index');
            Route::post('/', [CustomFieldDefinitionController::class, 'store'])->name('laravel-auto-crud.custom-fields.store');
            Route::put('/{id}', [CustomFieldDefinitionController::class, 'update'])->name('laravel-auto-crud.custom-fields.update');
            Route::delete('/{id}', [CustomFieldDefinitionController::class, 'destroy'])->name('laravel-auto-crud.custom-fields.destroy');
            Route::post('/reorder', [CustomFieldDefinitionController::class, 'reorder'])->name('laravel-auto-crud.custom-fields.reorder');
        });

        Route::post('/{model}/load-calendar-events', [CalendarController::class, 'loadEvents'])->name('laravel-auto-crud.model.load-calendar-events');
        Route::post('/{model}/load-autocomplete-items', [AutoCompleteController::class, 'loadAutocompleteItems'])->name('laravel-auto-crud.model.load-autocomplete-items');
        Route::post('/{model}/load-items', [AutoTableController::class, 'loadItems'])->name('laravel-auto-crud.model.load-items');
        Route::post('/{model}/{id}', [AutoCrudController::class, 'update'])->name('laravel-auto-crud.model.update');
        Route::post('/{model}/{id}/destroy', [AutoCrudController::class, 'destroy'])->name('laravel-auto-crud.model.destroy');
        Route::post('/{model}/{id}/permanent', [AutoCrudController::class, 'destroyPermanent'])->name('laravel-auto-crud.model.destroyPermanent');
        Route::post('/{model}/{id}/restore', [AutoCrudController::class, 'restore'])->name('laravel-auto-crud.model.restore');

        Route::get('/{model}/export-excel', [AutoCrudController::class, 'exportExcel'])->name('laravel-auto-crud.model.exportExcel');

        Route::get('/{model}/all', [AutoCompleteController::class, 'getAll'])->name('laravel-auto-crud.model.all');
        Route::get('/{model}/{id}', [AutoCrudController::class, 'getItem'])->name('laravel-auto-crud.model.getItem');
        
        Route::post('/{model}', [AutoCrudController::class, 'store'])->name('laravel-auto-crud.model.store');

        Route::post('/{model}/{id}/pivot/{externalRelation}/{item}', [AutoCrudController::class, 'updatePivot'])->name('laravel-auto-crud.model.updatePivot');
        Route::post('/{model}/{id}/bind/{externalRelation}/{item}', [AutoCrudController::class, 'bind'])->name('laravel-auto-crud.model.bind');
        Route::post('/{model}/{id}/unbind/{externalRelation}/{item}', [AutoCrudController::class, 'unbind'])->name('laravel-auto-crud.model.unbind');
    });
});
