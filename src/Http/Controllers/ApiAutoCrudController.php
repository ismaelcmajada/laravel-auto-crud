<?php

namespace Ismaelcmajada\LaravelAutoCrud\Http\Controllers;

use App\Http\Controllers\Controller;
use Ismaelcmajada\LaravelAutoCrud\Http\Requests\DynamicFormRequest;
use Ismaelcmajada\LaravelAutoCrud\Services\ApiResponseFactory;
use Ismaelcmajada\LaravelAutoCrud\Services\AutoCrudService;

class ApiAutoCrudController extends Controller
{
    protected $crud;
    protected $responses;
    protected $tables;
    protected $autocomplete;
    protected $calendar;

    public function __construct(
        AutoCrudService $crud,
        ApiResponseFactory $responses,
        AutoTableController $tables,
        AutoCompleteController $autocomplete,
        CalendarController $calendar
    ) {
        $this->crud = $crud;
        $this->responses = $responses;
        $this->tables = $tables;
        $this->autocomplete = $autocomplete;
        $this->calendar = $calendar;
    }

    public function schema($model)
    {
        return $this->responses->success($this->crud->schema($model, 'api'));
    }

    public function loadItems($model)
    {
        $result = $this->tables->loadItems($model);

        return $this->responses->success($result['tableData'], null, [
            'schema' => $this->crud->schema($model, 'api'),
        ]);
    }

    public function loadAutocompleteItems($model)
    {
        $result = $this->autocomplete->loadAutocompleteItems($model);

        return $this->responses->success($result['autocompleteItems']);
    }

    public function loadCalendarEvents($model)
    {
        $result = $this->calendar->loadEvents($model);

        return $this->responses->success($result['eventsData']);
    }

    public function getAll($model)
    {
        return $this->responses->success($this->autocomplete->getAll($model));
    }

    public function getItem($model, $id)
    {
        return $this->responses->success($this->crud->getItem($model, $id));
    }

    public function store(DynamicFormRequest $request, $model)
    {
        $result = $this->crud->store($request, $model);

        if (!$result['success']) {
            return $this->responses->error($result['message'], [], 422);
        }

        return $this->responses->success($result['data'], $result['message'], [], 201);
    }

    public function update(DynamicFormRequest $request, $model, $id)
    {
        $result = $this->crud->update($request, $model, $id);

        if (!$result['success']) {
            return $this->responses->error($result['message'], [], 422);
        }

        return $this->responses->success($result['data'], $result['message']);
    }

    public function destroy($model, $id)
    {
        $result = $this->crud->destroy($model, $id);

        if (!$result['success']) {
            return $this->responses->error($result['message'], [], 422);
        }

        return $this->responses->success($result['data'], $result['message']);
    }

    public function destroyPermanent($model, $id)
    {
        $result = $this->crud->destroyPermanent($model, $id);

        if (!$result['success']) {
            return $this->responses->error($result['message'], [], 422);
        }

        return $this->responses->success($result['data'], $result['message']);
    }

    public function restore($model, $id)
    {
        $result = $this->crud->restore($model, $id);

        if (!$result['success']) {
            return $this->responses->error($result['message'], [], 422);
        }

        return $this->responses->success($result['data'], $result['message']);
    }

    public function exportExcel($model)
    {
        return $this->responses->success($this->crud->exportExcel($model));
    }

    public function bind(DynamicFormRequest $request, $model, $id, $externalRelation, $item)
    {
        $result = $this->crud->bind($request, $model, $id, $externalRelation, $item);

        if (!$result['success']) {
            return $this->responses->error($result['message'], [], 422);
        }

        return $this->responses->success($result['data'], $result['message']);
    }

    public function updatePivot(DynamicFormRequest $request, $model, $id, $externalRelation, $item)
    {
        $result = $this->crud->updatePivot($request, $model, $id, $externalRelation, $item);

        if (!$result['success']) {
            return $this->responses->error($result['message'], [], 422);
        }

        return $this->responses->success($result['data'], $result['message']);
    }

    public function unbind($model, $id, $externalRelation, $item)
    {
        $result = $this->crud->unbind($model, $id, $externalRelation, $item);

        if (!$result['success']) {
            return $this->responses->error($result['message'], [], 422);
        }

        return $this->responses->success($result['data'], $result['message']);
    }
}
