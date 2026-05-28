<?php

namespace Ismaelcmajada\LaravelAutoCrud\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Ismaelcmajada\LaravelAutoCrud\Services\ApiResponseFactory;
use Ismaelcmajada\LaravelAutoCrud\Services\CustomFieldDefinitionService;

class ApiCustomFieldDefinitionController extends Controller
{
    protected $customFields;
    protected $responses;

    public function __construct(CustomFieldDefinitionService $customFields, ApiResponseFactory $responses)
    {
        $this->customFields = $customFields;
        $this->responses = $responses;
    }

    public function index($modelType)
    {
        return $this->responses->success($this->customFields->index($modelType));
    }

    public function store(Request $request, $modelType)
    {
        $definition = $this->customFields->store($request, $modelType);

        return $this->responses->success($definition, 'Campo personalizado creado.', [], 201);
    }

    public function update(Request $request, $modelType, $id)
    {
        $definition = $this->customFields->update($request, $modelType, $id);

        return $this->responses->success($definition, 'Campo personalizado actualizado.');
    }

    public function destroy($modelType, $id)
    {
        $this->customFields->destroy($modelType, $id);

        return $this->responses->success(null, 'Campo personalizado eliminado.');
    }

    public function reorder(Request $request, $modelType)
    {
        $this->customFields->reorder($request, $modelType);

        return $this->responses->success(null, 'Orden actualizado.');
    }

    public function getAvailableTypes()
    {
        return $this->responses->success($this->customFields->availableTypes());
    }
}
