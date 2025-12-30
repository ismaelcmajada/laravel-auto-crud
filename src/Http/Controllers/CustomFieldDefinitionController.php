<?php

namespace Ismaelcmajada\LaravelAutoCrud\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Ismaelcmajada\LaravelAutoCrud\Models\CustomFieldDefinition;

class CustomFieldDefinitionController extends Controller
{
    protected array $validTypes = [
        'string',
        'number',
        'text',
        'boolean',
        'date',
        'datetime',
        'select',
    ];

    public function index(string $modelType)
    {
        $decodedModel = $this->decodeModelType($modelType);

        return CustomFieldDefinition::where('model_type', $decodedModel)
            ->orderBy('order')
            ->get();
    }

    public function store(Request $request, string $modelType)
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

        $name = $validated['name'] ?? Str::slug($validated['label'], '_');

        // Verificar unicidad
        $existingCount = CustomFieldDefinition::where('model_type', $decodedModel)
            ->where('name', 'like', $name . '%')
            ->count();

        if ($existingCount > 0) {
            $name = $name . '_' . ($existingCount + 1);
        }

        $definition = CustomFieldDefinition::create([
            'model_type' => $decodedModel,
            'name' => $name,
            'label' => $validated['label'],
            'type' => $validated['type'],
            'options' => $validated['options'] ?? null,
            'rules' => $validated['rules'] ?? null,
            'order' => $validated['order'] ?? CustomFieldDefinition::where('model_type', $decodedModel)->max('order') + 1,
            'is_active' => $validated['is_active'] ?? true,
            'show_in_table' => $validated['show_in_table'] ?? false,
        ]);

        return Redirect::back()->with(['success' => 'Campo personalizado creado.', 'data' => $definition]);
    }

    public function update(Request $request, string $modelType, int $id)
    {
        $definition = CustomFieldDefinition::findOrFail($id);

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

        return Redirect::back()->with(['success' => 'Campo personalizado actualizado.', 'data' => $definition]);
    }

    public function destroy(string $modelType, int $id)
    {
        $definition = CustomFieldDefinition::findOrFail($id);
        $definition->delete();

        return Redirect::back()->with('success', 'Campo personalizado eliminado.');
    }

    public function reorder(Request $request, string $modelType)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:custom_field_definitions,id',
        ]);

        foreach ($validated['order'] as $index => $id) {
            CustomFieldDefinition::where('id', $id)->update(['order' => $index]);
        }

        return Redirect::back()->with('success', 'Orden actualizado.');
    }

    public function getAvailableTypes()
    {
        return response()->json([
            ['value' => 'string', 'label' => 'Texto corto'],
            ['value' => 'number', 'label' => 'Número'],
            ['value' => 'text', 'label' => 'Texto largo'],
            ['value' => 'boolean', 'label' => 'Sí/No'],
            ['value' => 'date', 'label' => 'Fecha'],
            ['value' => 'datetime', 'label' => 'Fecha y hora'],
            ['value' => 'select', 'label' => 'Selección'],
        ]);
    }

    protected function decodeModelType(string $modelType): string
    {
        // Convertir de formato URL a namespace
        // Ej: "product" -> "App\Models\Product"
        return 'App\\Models\\' . ucfirst($modelType);
    }
}
