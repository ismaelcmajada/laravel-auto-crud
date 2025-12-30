<?php

namespace Ismaelcmajada\LaravelAutoCrud\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use App\Models\Record;
use Illuminate\Support\Facades\Auth;
use Ismaelcmajada\LaravelAutoCrud\Http\Requests\DynamicFormRequest;

class AutoCrudController extends Controller
{
    private function getModel($model)
    {
        $modelClass = 'App\\Models\\' . ucfirst($model);

        if (class_exists($modelClass)) {
            return new $modelClass;
        } else {
            abort(404, 'Model not found');
        }
    }

    public function getItem($model, $id)
    {
        $modelInstance = $this->getModel($model);
        $item = $modelInstance::findOrFail($id);

        $item->load($modelInstance::getIncludes());

        // Agregar custom fields si están habilitados
        if ($modelInstance::hasCustomFieldsEnabled()) {
            $customValues = $item->getCustomFieldsValues();
            foreach ($customValues as $key => $value) {
                $item->setAttribute($key, $value);
            }
        }

        return $item;
    }

    public function index($model)
    {
        return Inertia::render('Dashboard/' . ucfirst($model));
    }

    public function store(DynamicFormRequest $request, $model)
    {
        $validatedData = $request->validated();
        $modelInstance = $this->getModel($model);

        foreach ($modelInstance::getFormFields() as $field) {
            if ($field['type'] === 'select' && isset($field['multiple']) && $field['multiple']) {
                $validatedData[$field['field']] = implode(', ', $validatedData[$field['field']]);
            }
            // Excluir campos de archivos múltiples del create (se manejan después)
            if (($field['type'] === 'image' || $field['type'] === 'file') && isset($field['multiple']) && $field['multiple']) {
                unset($validatedData[$field['field']]);
            }
        }

        // Excluir custom fields del modelo principal (se guardan aparte)
        $validatedData = array_filter($validatedData, fn($value, $key) => !str_starts_with($key, 'custom_'), ARRAY_FILTER_USE_BOTH);

        $instance = $modelInstance::create($validatedData);

        // Manejo de archivos
        foreach ($modelInstance::getFormFields() as $field) {
            if (($field['type'] === 'image' || $field['type'] === 'file') && $request->hasFile($field['field'])) {
                $storagePath = $field['public'] ? 'public/' : 'private/';
                $storagePath .= $field['type'] === 'image' ? 'images/' : 'files/';
                $storagePath .= $model;

                // Múltiples archivos
                if (isset($field['multiple']) && $field['multiple'] && is_array($request->file($field['field']))) {
                    $filePaths = [];
                    foreach ($request->file($field['field']) as $index => $file) {
                        $fileName = $field['field'] . '/' . $instance['id'] . '_' . $index . '_' . $file->getClientOriginalName();
                        $filePath = $file->storeAs($storagePath, $fileName);

                        if (!$field['public'] && $field['type'] === 'file') {
                            $fileContent = Storage::get($filePath);
                            $encryptedContent = Crypt::encryptString($fileContent);
                            Storage::put($filePath, $encryptedContent);
                        }

                        $filePaths[] = $filePath;
                    }
                    $instance->{$field['field']} = json_encode($filePaths);
                } else {
                    // Archivo único
                    $filePath = $request->file($field['field'])->storeAs($storagePath,  $field['field'] . '/' . $instance['id']);

                    if (!$field['public'] && $field['type'] === 'file') {
                        $fileContent = Storage::get($filePath);
                        $encryptedContent = Crypt::encryptString($fileContent);
                        Storage::put($filePath, $encryptedContent);
                    }

                    $instance->{$field['field']} = $filePath;
                }
            }
        }

        $created = $instance->save();

        // Guardar custom fields si están habilitados
        if ($modelInstance::hasCustomFieldsEnabled()) {
            $instance->saveCustomFields($request->all());
        }

        $instance->load($modelInstance::getIncludes());

        // Agregar custom fields a la respuesta
        if ($modelInstance::hasCustomFieldsEnabled()) {
            $customValues = $instance->getCustomFieldsValues();
            foreach ($customValues as $key => $value) {
                $instance->setAttribute($key, $value);
            }
        }

        if ($created) {
            $this->setRecord($model, $instance->id, 'create');
            return Redirect::back()->with(['success' => 'Elemento creado.', 'data' => $instance]);
        }
    }

    public function update(DynamicFormRequest $request, $model, $id)
    {
        $instance = $this->getModel($model)::findOrFail($id);
        $validatedData = $request->validated();

        foreach ($instance::getFormFields() as $field) {
            if ($field['type'] === 'image' || $field['type'] === 'file') {
                // Múltiples archivos - guardado aditivo
                if (isset($field['multiple']) && $field['multiple']) {
                    $existingFiles = json_decode($instance->{$field['field']}, true) ?? [];
                    
                    // Eliminar archivos marcados para borrar
                    $filesToDelete = $request->input($field['field'] . '_delete', []);
                    if (!empty($filesToDelete)) {
                        foreach ($filesToDelete as $fileToDelete) {
                            Storage::delete($fileToDelete);
                            $existingFiles = array_filter($existingFiles, fn($f) => $f !== $fileToDelete);
                        }
                        $existingFiles = array_values($existingFiles); // Reindexar
                    }
                    
                    // Añadir nuevos archivos
                    if ($request->hasFile($field['field'])) {
                        $storagePath = $field['public'] ? 'public/' : 'private/';
                        $storagePath .= 'files/' . $model;
                        
                        foreach ($request->file($field['field']) as $file) {
                            $fileName = $field['field'] . '/' . $id . '_' . time() . '_' . $file->getClientOriginalName();
                            $filePath = $file->storeAs($storagePath, $fileName);

                            if (!$field['public']) {
                                $fileContent = Storage::get($filePath);
                                $encryptedContent = Crypt::encryptString($fileContent);
                                Storage::put($filePath, $encryptedContent);
                            }

                            $existingFiles[] = $filePath;
                        }
                    }
                    
                    $validatedData[$field['field']] = !empty($existingFiles) ? json_encode($existingFiles) : null;
                    
                } else {
                    // Archivo único (comportamiento original)
                    if ($request->input($field['field'] . '_edited')) {
                        Storage::delete($field['public'] ? 'public/images/' . $model . '/' . $field['field'] . '/' . $id : 'private/images/' . $model . '/' . $field['field'] . '/' . $id);
                        $validatedData[$field['field']] = null;
                    }
                    if ($request->hasFile($field['field'])) {
                        $storagePath = $field['public'] ? 'public/' : 'private/';
                        $storagePath .= $field['type'] === 'image' ? 'images/' : 'files/';
                        $storagePath .= $model;
                        $filePath = $request->file($field['field'])->storeAs($storagePath, $field['field'] . '/' . $id);

                        if (!$field['public'] && $field['type'] === 'file') {
                            $fileContent = Storage::get($filePath);
                            $encryptedContent = Crypt::encryptString($fileContent);
                            Storage::put($filePath, $encryptedContent);
                        }

                        $validatedData[$field['field']] = $filePath;
                    }
                }
            }

            if ($field['type'] === 'select' && isset($field['multiple']) && $field['multiple']) {
                $validatedData[$field['field']] = implode(', ', $validatedData[$field['field']]);
            }

            if ($field['type'] === 'password' && !$request->input($field['field'])) {
                unset($validatedData[$field['field']]);
            }
        }

        // Excluir custom fields del modelo principal (se guardan aparte)
        $validatedData = array_filter($validatedData, fn($value, $key) => !str_starts_with($key, 'custom_'), ARRAY_FILTER_USE_BOTH);

        $updated = $instance->update($validatedData);

        // Guardar custom fields si están habilitados
        if ($instance::hasCustomFieldsEnabled()) {
            $instance->saveCustomFields($request->all());
        }

        $instance->load($instance::getIncludes());

        // Agregar custom fields a la respuesta
        if ($instance::hasCustomFieldsEnabled()) {
            $customValues = $instance->getCustomFieldsValues();
            foreach ($customValues as $key => $value) {
                $instance->setAttribute($key, $value);
            }
        }

        if ($updated) {
            $this->setRecord($model, $instance->id, 'update');
            return Redirect::back()->with(['success' => 'Elemento editado.', 'data' => $instance]);
        }
    }


    public function destroy($model, $id)
    {
        $instance = $this->getModel($model)::findOrFail($id);

        if ($instance->delete()) {

            $this->setRecord($model, $instance->id, 'destroy');

            return Redirect::back()->with('success', 'Elemento movido a la papelera.');
        }
    }

    public function destroyPermanent($model, $id)
    {
        $instance = $this->getModel($model)::onlyTrashed()->findOrFail($id);
        foreach ($instance::getFormFields() as $field) {
            if ($field['type'] === 'image') {
                Storage::delete($field['public'] ? 'public/images/' . $model . '/' . $field['field'] . '/' . $id : 'private/images/' . $model . '/' . $field['field'] . '/' . $id);
            }

            if ($field['type'] === 'file') {
                Storage::delete($field['public'] ? 'public/files/' . $model . '/' . $field['field'] . '/' . $id : 'private/files/' . $model . '/' . $field['field'] . '/' . $id);
            }
        }

        if ($instance->forceDelete()) {

            $this->setRecord($model, $instance->id, 'destroyPermanent');

            return Redirect::back()->with('success', 'Elemento eliminado de forma permanente.');
        }
    }

    public function restore($model, $id)
    {
        $instance = $this->getModel($model)::onlyTrashed()->findOrFail($id);

        if ($instance->restore()) {

            $this->setRecord($model, $instance->id, 'restore');

            return Redirect::back()->with('success', 'Elemento restaurado.');
        }
    }

    public function exportExcel($model)
    {
        $items = $this->getModel($model)::all();

        return  ['itemsExcel' => $items];
    }

    public function bind(DynamicFormRequest $request, $model, $id, $externalRelation, $item)
    {
        $instance = $this->getModel($model)::findOrFail($id);
        $validatedData = $request->validated();

        $instance->{$externalRelation}()->attach($item, $validatedData);

        $instance->load($instance::getIncludes());

        $this->setRecord($model, $instance->id, 'update');

        return Redirect::back()->with(['success' => 'Elemento vinculado', 'data' => $instance]);
    }

    public function updatePivot(DynamicFormRequest $request, $model, $id, $externalRelation, $item)
    {
        $instance = $this->getModel($model)::findOrFail($id);
        $validatedData = $request->validated();

        $instance->{$externalRelation}()->updateExistingPivot($item, $validatedData);

        $instance->load($instance::getIncludes());

        $this->setRecord($model, $instance->id, 'update');
        return Redirect::back()->with(['success' => 'Elemento actualizado', 'data' => $instance]);
    }

    public function unbind($model, $id, $externalRelation, $item)
    {
        $instance = $this->getModel($model)::findOrFail($id);
        $instance->{$externalRelation}()->detach($item);

        $instance->load($instance::getIncludes());

        $this->setRecord($model, $instance->id, 'update');
        return Redirect::back()->with(['success' => 'Elemento desvinculado', 'data' => $instance]);
    }

    public function setRecord($model, $element_id, $action)
    {
        $record = new Record();
        $record->user_id = Auth::user()->id;
        $record->element_id = $element_id;
        $record->action = $action;
        $record->model = 'App\\Models\\' . ucfirst($model);

        $record->save();
    }
}
