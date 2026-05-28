<?php

namespace Ismaelcmajada\LaravelAutoCrud\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Ismaelcmajada\LaravelAutoCrud\Models\CustomFieldDefinition;

class CustomFieldDefinitionService
{
    protected $validTypes = [
        'string',
        'number',
        'text',
        'boolean',
        'date',
        'datetime',
        'select',
    ];

    public function index($modelType)
    {
        return CustomFieldDefinition::where('model_type', $this->decodeModelType($modelType))
            ->orderBy('order')
            ->get();
    }

    public function store($request, $modelType)
    {
        $decodedModel = $this->decodeModelType($modelType);

        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'name' => 'nullable|string|max:255',
            'type' => 'required|string|in:' . implode(',', $this->validTypes),
            'options' => 'nullable|array',
            'rules' => 'nullable|array',
            'order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'show_in_table' => 'nullable|boolean',
        ]);

        $name = isset($validated['name']) ? $validated['name'] : Str::slug($validated['label'], '_');
        $existingCount = CustomFieldDefinition::where('model_type', $decodedModel)
            ->where('name', 'like', $name . '%')
            ->count();

        if ($existingCount > 0) {
            $name = $name . '_' . ($existingCount + 1);
        }

        return CustomFieldDefinition::create([
            'model_type' => $decodedModel,
            'name' => $name,
            'label' => $validated['label'],
            'type' => $validated['type'],
            'options' => isset($validated['options']) ? $validated['options'] : null,
            'rules' => isset($validated['rules']) ? $validated['rules'] : null,
            'order' => isset($validated['order']) ? $validated['order'] : CustomFieldDefinition::where('model_type', $decodedModel)->max('order') + 1,
            'is_active' => isset($validated['is_active']) ? $validated['is_active'] : true,
            'show_in_table' => isset($validated['show_in_table']) ? $validated['show_in_table'] : false,
        ]);
    }

    public function update($request, $modelType, $id)
    {
        $definition = $this->queryForModel($modelType)->findOrFail($id);

        $validated = $request->validate([
            'label' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|in:' . implode(',', $this->validTypes),
            'options' => 'nullable|array',
            'rules' => 'nullable|array',
            'order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'show_in_table' => 'nullable|boolean',
        ]);

        $definition->update($validated);

        return $definition;
    }

    public function destroy($modelType, $id)
    {
        $definition = $this->queryForModel($modelType)->findOrFail($id);
        $definition->delete();

        return $definition;
    }

    public function reorder($request, $modelType)
    {
        $decodedModel = $this->decodeModelType($modelType);

        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:custom_field_definitions,id',
        ]);

        $ids = array_values(array_unique($validated['order']));
        $existingCount = CustomFieldDefinition::where('model_type', $decodedModel)
            ->whereIn('id', $ids)
            ->count();

        if ($existingCount !== count($ids)) {
            throw ValidationException::withMessages([
                'order' => ['El orden contiene campos que no pertenecen al modelo indicado.'],
            ]);
        }

        foreach ($validated['order'] as $index => $id) {
            CustomFieldDefinition::where('model_type', $decodedModel)
                ->where('id', $id)
                ->update(['order' => $index]);
        }
    }

    public function availableTypes()
    {
        return [
            ['value' => 'string', 'label' => 'Texto corto'],
            ['value' => 'number', 'label' => 'Número'],
            ['value' => 'text', 'label' => 'Texto largo'],
            ['value' => 'boolean', 'label' => 'Sí/No'],
            ['value' => 'date', 'label' => 'Fecha'],
            ['value' => 'datetime', 'label' => 'Fecha y hora'],
            ['value' => 'select', 'label' => 'Selección'],
        ];
    }

    protected function decodeModelType($modelType)
    {
        return 'App\\Models\\' . Str::studly($modelType);
    }

    protected function queryForModel($modelType)
    {
        return CustomFieldDefinition::where('model_type', $this->decodeModelType($modelType));
    }
}
