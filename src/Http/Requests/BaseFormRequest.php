<?php

namespace Ismaelcmajada\LaravelAutoCrud\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

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
        $externalRelation = $this->route('externalRelation');

        return $this->getValidationRules($modelInstance, $id, $itemId, $externalRelation);
    }

    protected function getValidationRules($modelInstance, $id = null, $itemId = null, $externalRelation = null)
    {
        $rules = [];

        // Si NO hay $itemId, validamos los campos "propios" del modelo (no pivot).
        if (!$itemId) {
            foreach ($modelInstance::getFormFields() as $field) {
                $fieldRules = $this->buildFieldRules($field, $modelInstance, $id);
                $rules[$field['field']] = $fieldRules;
            }
            return $rules;
        }

        // Si SÍ hay $itemId, entonces validamos la tabla pivot de UNA SOLA relación:
        foreach ($modelInstance::getExternalRelations() as $relation) {
            // Verificamos si el 'relation' del modelo coincide con el 'externalRelation' del request
            if ($relation['relation'] !== $externalRelation) {
                continue; // ignorar el resto
            }

            // Para la relación que coincide, construimos las reglas de sus pivotFields
            if (isset($relation['pivotFields'])) {
                foreach ($relation['pivotFields'] as $pivotField) {
                    $fieldRules = $this->buildFieldRules(
                        $pivotField,
                        $modelInstance,
                        $id,
                        $relation,
                        $itemId
                    );
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
                // Solo aplica las reglas de imagen si se envía un archivo
                $fieldRules[] = function ($attribute, $value, $fail) use ($field) {
                    // Si no se envió nada o es null, no validamos como imagen
                    if ($value === null || $value === '') {
                        return;
                    }
                    
                    // Si es un archivo, validamos con las reglas de imagen
                    if (is_file($value) || is_object($value)) {
                        $validator = Validator::make([$attribute => $value], [
                            $attribute => ['image']
                        ]);
                        
                        if ($validator->fails()) {
                            $fail($validator->errors()->first($attribute));
                        }
                        
                        // Validar max si está definido
                        if (isset($field['rules']['max'])) {
                            $validator = Validator::make([$attribute => $value], [
                                $attribute => ['max:'.$field['rules']['max']]
                            ]);
                            
                            if ($validator->fails()) {
                                $fail($validator->errors()->first($attribute));
                            }
                        }
                        
                        // Validar mimes si está definido
                        if (isset($field['rules']['mimes'])) {
                            $validator = Validator::make([$attribute => $value], [
                                $attribute => ['mimes:'.$field['rules']['mimes']]
                            ]);
                            
                            if ($validator->fails()) {
                                $fail($validator->errors()->first($attribute));
                            }
                        }
                    }
                };
                break;
            case 'file':
                if (isset($field['multiple']) && $field['multiple']) {
                    // Múltiples archivos
                    $fieldRules[] = 'array';
                    $fieldRules[] = function ($attribute, $value, $fail) use ($field) {
                        if (!is_array($value)) {
                            return;
                        }
                        foreach ($value as $index => $file) {
                            if (!is_file($file) && !is_object($file)) {
                                continue;
                            }
                            $rules = ['file'];
                            if (isset($field['rules']['max'])) {
                                $rules[] = 'max:' . $field['rules']['max'];
                            }
                            if (isset($field['rules']['mimes'])) {
                                $rules[] = 'mimes:' . $field['rules']['mimes'];
                            }
                            $validator = Validator::make([$attribute => $file], [
                                $attribute => $rules
                            ]);
                            if ($validator->fails()) {
                                $fail("Archivo {$index}: " . $validator->errors()->first($attribute));
                            }
                        }
                    };
                } else {
                    // Archivo único
                    $fieldRules[] = 'file';
                    if (isset($field['rules']['max'])) {
                        $fieldRules[] = 'max:' . $field['rules']['max'];
                    }
                    if (isset($field['rules']['mimes'])) {
                        $fieldRules[] = 'mimes:' . $field['rules']['mimes'];
                    }
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

            // Iterar sobre cada regla personalizada definida en el array 'custom'
            foreach ($field['rules']['custom'] as $customRule) {
                if (isset($customRules[$customRule])) {
                    $fieldRules[] = $customRules[$customRule];
                }
            }
        }

        return $fieldRules;
    }
}
