<?php

namespace Ismaelcmajada\LaravelAutoCrud\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class BaseFormRequest extends FormRequest
{
    protected $modelClass;

    public function authorize()
    {
        // Aquí puedes agregar lógica de autorización si es necesario
        return true;
    }

    public function rules()
    {
        $modelInstance = new $this->modelClass;
        $id = $this->route('id'); // Asumiendo que el ID del modelo viene en la ruta
        $itemId = $this->route('item'); // Para relaciones pivot, si aplica

        return $this->getValidationRules($modelInstance, $id, $itemId);
    }

    protected function getValidationRules($modelInstance, $id = null, $itemId = null)
    {
        $rules = [];

        if (!$itemId) {
            foreach ($modelInstance::getFormFields() as $field) {
                $fieldRules = $this->buildFieldRules($field, $modelInstance, $id);
                $rules[$field['field']] = $fieldRules;
            }

            return $rules;
        }

        foreach ($modelInstance::getExternalRelations() as $relation) {
            if (isset($relation['pivotFields'])) {
                foreach ($relation['pivotFields'] as $pivotField) {
                    $fieldRules = $this->buildFieldRules($pivotField, $modelInstance, $id, $relation, $itemId);
                    $rules[$pivotField['field']] = $fieldRules;
                }
            }
        }

        return $rules;
    }

    private function buildFieldRules($field, $modelInstance, $id, $relation = null, $itemId = null)
    {
        $fieldRules = [];

        if (isset($field['rules']['required']) && $field['rules']['required']) {
            if ($field['type'] !== 'image' && $field['type'] !== 'password') {
                $fieldRules[] = 'required';
            }
        } else {
            $fieldRules[] = 'nullable';
        }

        switch ($field['type']) {
            case 'string':
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:191';
                break;
            case 'email':
                $fieldRules[] = 'email';
                $fieldRules[] = 'max:191';
                break;
            case 'number':
                $fieldRules[] = 'integer';
                break;
            case 'decimal':
                $fieldRules[] = 'decimal';
                break;
            case 'select':
                if (isset($field['options'])) {
                    if (isset($field['multiple']) && $field['multiple']) {
                        $fieldRules[] = 'array';
                        $fieldRules[] = function ($attribute, $value, $fail) use ($field) {
                            foreach ($value as $option) {
                                if (!in_array(trim($option), $field['options'])) {
                                    $fail("La opción seleccionada '{$option}' no es válida.");
                                }
                            }
                        };
                    } else {
                        $fieldRules[] = Rule::in($field['options']);
                    }
                }
                break;
            case 'telephone':
                $fieldRules[] = 'digits_between:8,15';
                break;
            case 'image':
                $fieldRules[] = 'image';
                if (isset($field['rules']['max'])) {
                    $fieldRules[] = 'max:' . $field['rules']['max'];
                }
                if (isset($field['rules']['mimes'])) {
                    $fieldRules[] = 'mimes:' . $field['rules']['mimes'];
                }
                break;
            case 'file':
                $fieldRules[] = 'file';
                if (isset($field['rules']['max'])) {
                    $fieldRules[] = 'max:' . $field['rules']['max'];
                }
                if (isset($field['rules']['mimes'])) {
                    $fieldRules[] = 'mimes:' . $field['rules']['mimes'];
                }
                break;
        }

        if (isset($field['rules']['unique']) && $field['rules']['unique']) {
            if ($relation) {
                $uniqueRule = Rule::unique($relation['pivotTable'], $field['field'])->where(function ($query) use ($field, $relation, $id, $itemId) {
                    if ($field['type'] === 'boolean') {
                        $query->where($field['field'], '=', true)
                            ->where($relation['foreignKey'], '=', $id)
                            ->where($relation['relatedKey'], '!=', $itemId);
                    }
                });
            } else {
                $uniqueRule = Rule::unique($modelInstance->getTable(), $field['field'])->where(function ($query) use ($field) {
                    if ($field['type'] === 'boolean') {
                        $query->where($field['field'], '=', true);
                    }
                });

                if ($id !== null) {
                    $uniqueRule = $uniqueRule->ignore($id);
                }
            }

            $fieldRules[] = $uniqueRule;
        }

        // Añadir reglas personalizadas definidas en el modelo mediante getCustomRules
        if (isset($field['rules']['custom']) && is_array($field['rules']['custom'])) {
            $customRules = $modelInstance::getCustomRules();
            $request = $this;

            // Iterar sobre cada regla personalizada definida en el array 'custom'
            foreach ($field['rules']['custom'] as $customRule) {
                if (isset($customRules[$customRule])) {
                    // Modificamos para enviar el request completo a la función de validación
                    $originalRule = $customRules[$customRule];
                    $fieldRules[] = function ($attribute, $value, $fail) use ($originalRule, $request) {
                        // Llamamos a la regla original, pero le pasamos la instancia del request
                        $originalRule($attribute, $value, $fail, $request);
                    };
                }
            }
        }

        return $fieldRules;
    }
}
