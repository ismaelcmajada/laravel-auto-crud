<?php

namespace Ismaelcmajada\LaravelAutoCrud\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Ismaelcmajada\LaravelAutoCrud\Http\Requests\DynamicFormRequest;
use Ismaelcmajada\LaravelAutoCrud\Services\AutoCrudService;

class AutoCrudController extends Controller
{
    protected $crud;

    public function __construct(AutoCrudService $crud)
    {
        $this->crud = $crud;
    }

    public function getItem($model, $id)
    {
        return $this->crud->getItem($model, $id);
    }

    public function index($model)
    {
        return Inertia::render('Dashboard/' . ucfirst($model));
    }

    public function store(DynamicFormRequest $request, $model)
    {
        $result = $this->crud->store($request, $model);

        if (!$result['success']) {
            return $this->failedResponse($result);
        }

        return Redirect::back()->with([
            'success' => $result['message'],
            'data' => $result['data'],
        ]);
    }

    public function update(DynamicFormRequest $request, $model, $id)
    {
        $result = $this->crud->update($request, $model, $id);

        if (!$result['success']) {
            return $this->failedResponse($result);
        }

        return Redirect::back()->with([
            'success' => $result['message'],
            'data' => $result['data'],
        ]);
    }

    public function destroy($model, $id)
    {
        $result = $this->crud->destroy($model, $id);

        if (!$result['success']) {
            return $this->failedResponse($result);
        }

        return Redirect::back()->with('success', $result['message']);
    }

    public function destroyPermanent($model, $id)
    {
        $result = $this->crud->destroyPermanent($model, $id);

        if (!$result['success']) {
            return $this->failedResponse($result);
        }

        return Redirect::back()->with('success', $result['message']);
    }

    public function restore($model, $id)
    {
        $result = $this->crud->restore($model, $id);

        if (!$result['success']) {
            return $this->failedResponse($result);
        }

        return Redirect::back()->with('success', $result['message']);
    }

    public function exportExcel($model)
    {
        return $this->crud->exportExcel($model);
    }

    public function bind(DynamicFormRequest $request, $model, $id, $externalRelation, $item)
    {
        $result = $this->crud->bind($request, $model, $id, $externalRelation, $item);

        if (!$result['success']) {
            return $this->failedResponse($result);
        }

        return Redirect::back()->with([
            'success' => $result['message'],
            'data' => $result['data'],
        ]);
    }

    public function updatePivot(DynamicFormRequest $request, $model, $id, $externalRelation, $item)
    {
        $result = $this->crud->updatePivot($request, $model, $id, $externalRelation, $item);

        if (!$result['success']) {
            return $this->failedResponse($result);
        }

        return Redirect::back()->with([
            'success' => $result['message'],
            'data' => $result['data'],
        ]);
    }

    public function unbind($model, $id, $externalRelation, $item)
    {
        $result = $this->crud->unbind($model, $id, $externalRelation, $item);

        if (!$result['success']) {
            return $this->failedResponse($result);
        }

        return Redirect::back()->with([
            'success' => $result['message'],
            'data' => $result['data'],
        ]);
    }

    public function setRecord($model, $element_id, $action)
    {
        $this->crud->setRecord($model, $element_id, $action);
    }

    protected function failedResponse(array $result)
    {
        if (session()->has('errors')) {
            return Redirect::back();
        }

        return Redirect::back()->withErrors(['error' => $result['message']]);
    }
}
