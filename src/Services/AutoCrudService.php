<?php

namespace Ismaelcmajada\LaravelAutoCrud\Services;

use App\Models\Record;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Ismaelcmajada\LaravelAutoCrud\Events\AutoCrudActionCompleted;

class AutoCrudService
{
    protected $models;

    public function __construct(AutoCrudModelResolver $models)
    {
        $this->models = $models;
    }

    public function resolveModel($model)
    {
        return $this->models->resolve($model);
    }

    public function schema($model, $context = 'web')
    {
        $modelInstance = $this->resolveModel($model);

        return $modelInstance::getModel([], $context);
    }

    public function getItem($model, $id)
    {
        $modelInstance = $this->resolveModel($model);
        $item = $modelInstance::findOrFail($id);

        $item->load($modelInstance::getIncludes());
        $this->addCustomFieldsToItem($item);

        return $item;
    }

    public function store($request, $model)
    {
        $validatedData = $request->validated();
        $modelInstance = $this->resolveModel($model);

        foreach ($modelInstance::getFormFields() as $field) {
            if ($field['type'] === 'select' && isset($field['multiple']) && $field['multiple'] && isset($validatedData[$field['field']]) && is_array($validatedData[$field['field']])) {
                $validatedData[$field['field']] = implode(', ', $validatedData[$field['field']]);
            }

            if ($field['type'] === 'image' || $field['type'] === 'file') {
                unset($validatedData[$field['field']]);
            }
        }

        $validatedData = $this->withoutCustomFields($validatedData);
        $instance = $modelInstance::create($validatedData);

        $this->storeUploadedFiles($request, $model, $modelInstance, $instance);

        $created = $instance->save();

        if ($modelInstance::hasCustomFieldsEnabled()) {
            $instance->saveCustomFields($this->requestDataWithoutUploadedFiles($request));
        }

        $instance->load($modelInstance::getIncludes());
        $this->addCustomFieldsToItem($instance);

        if ($created) {
            $this->setRecord($model, $instance->id, 'create');
            AutoCrudActionCompleted::dispatch('store', $model, $instance, $validatedData);
        }

        return [
            'message' => 'Elemento creado.',
            'data' => $instance,
        ];
    }

    public function update($request, $model, $id)
    {
        $instance = $this->resolveModel($model)::findOrFail($id);
        $validatedData = $request->validated();

        foreach ($instance::getFormFields() as $field) {
            if ($field['type'] === 'image' || $field['type'] === 'file') {
                $this->prepareFileUpdate($request, $model, $id, $instance, $field, $validatedData);
            }

            if ($field['type'] === 'select' && isset($field['multiple']) && $field['multiple'] && isset($validatedData[$field['field']]) && is_array($validatedData[$field['field']])) {
                $validatedData[$field['field']] = implode(', ', $validatedData[$field['field']]);
            }

            if ($field['type'] === 'password' && !$request->input($field['field'])) {
                unset($validatedData[$field['field']]);
            }
        }

        $validatedData = $this->withoutCustomFields($validatedData);
        $updated = $instance->update($validatedData);

        if ($instance::hasCustomFieldsEnabled()) {
            $instance->saveCustomFields($request->all());
        }

        $instance->load($instance::getIncludes());
        $this->addCustomFieldsToItem($instance);

        if ($updated) {
            $this->setRecord($model, $instance->id, 'update');
            AutoCrudActionCompleted::dispatch('update', $model, $instance, $validatedData);
        }

        return [
            'message' => 'Elemento editado.',
            'data' => $instance,
        ];
    }

    public function destroy($model, $id)
    {
        $instance = $this->resolveModel($model)::findOrFail($id);

        if ($instance->delete()) {
            $this->setRecord($model, $instance->id, 'destroy');
            AutoCrudActionCompleted::dispatch('destroy', $model, $instance);
        }

        return [
            'message' => 'Elemento movido a la papelera.',
            'data' => $instance,
        ];
    }

    public function destroyPermanent($model, $id)
    {
        $instance = $this->resolveModel($model)::onlyTrashed()->findOrFail($id);

        foreach ($instance::getFormFields() as $field) {
            if (in_array($field['type'], ['image', 'file']) && !empty($instance->{$field['field']})) {
                if (isset($field['multiple']) && $field['multiple']) {
                    $filePaths = json_decode($instance->{$field['field']}, true) ?: [];
                    foreach ($filePaths as $filePath) {
                        Storage::delete($filePath);
                    }
                } else {
                    Storage::delete($instance->{$field['field']});
                }
            }
        }

        if ($instance::hasCustomFieldsEnabled()) {
            $instance->customFieldValues()->delete();
        }

        if ($instance->forceDelete()) {
            $this->setRecord($model, $instance->id, 'destroyPermanent');
            AutoCrudActionCompleted::dispatch('destroyPermanent', $model, $instance);
        }

        return [
            'message' => 'Elemento eliminado de forma permanente.',
            'data' => $instance,
        ];
    }

    public function restore($model, $id)
    {
        $instance = $this->resolveModel($model)::onlyTrashed()->findOrFail($id);

        if ($instance->restore()) {
            $this->setRecord($model, $instance->id, 'restore');
            AutoCrudActionCompleted::dispatch('restore', $model, $instance);
        }

        return [
            'message' => 'Elemento restaurado.',
            'data' => $instance,
        ];
    }

    public function exportExcel($model)
    {
        return ['itemsExcel' => $this->resolveModel($model)::all()];
    }

    public function bind($request, $model, $id, $externalRelation, $item)
    {
        $instance = $this->resolveModel($model)::findOrFail($id);
        $validatedData = $request->validated();

        $instance->{$externalRelation}()->attach($item, $validatedData);
        $instance->load($instance::getIncludes());

        $this->setRecord($model, $instance->id, 'update');
        AutoCrudActionCompleted::dispatch('bind', $model, $instance, $validatedData, ['externalRelation' => $externalRelation, 'item' => $item]);

        return [
            'message' => 'Elemento vinculado',
            'data' => $instance,
        ];
    }

    public function updatePivot($request, $model, $id, $externalRelation, $item)
    {
        $instance = $this->resolveModel($model)::findOrFail($id);
        $validatedData = $request->validated();

        $instance->{$externalRelation}()->updateExistingPivot($item, $validatedData);
        $instance->load($instance::getIncludes());

        $this->setRecord($model, $instance->id, 'update');
        AutoCrudActionCompleted::dispatch('updatePivot', $model, $instance, $validatedData, ['externalRelation' => $externalRelation, 'item' => $item]);

        return [
            'message' => 'Elemento actualizado',
            'data' => $instance,
        ];
    }

    public function unbind($model, $id, $externalRelation, $item)
    {
        $instance = $this->resolveModel($model)::findOrFail($id);

        $instance->{$externalRelation}()->detach($item);
        $instance->load($instance::getIncludes());

        $this->setRecord($model, $instance->id, 'update');
        AutoCrudActionCompleted::dispatch('unbind', $model, $instance, [], ['externalRelation' => $externalRelation, 'item' => $item]);

        return [
            'message' => 'Elemento desvinculado',
            'data' => $instance,
        ];
    }

    public function setRecord($model, $elementId, $action)
    {
        $record = new Record();
        $record->user_id = Auth::user() ? Auth::user()->id : null;
        $record->element_id = $elementId;
        $record->action = $action;
        $record->model = $this->models->className($model);

        $record->save();
    }

    protected function storeUploadedFiles($request, $model, $modelInstance, $instance)
    {
        foreach ($modelInstance::getFormFields() as $field) {
            if (($field['type'] === 'image' || $field['type'] === 'file') && $request->hasFile($field['field'])) {
                $storagePath = $this->storagePath($field, $model);

                if (isset($field['multiple']) && $field['multiple'] && is_array($request->file($field['field']))) {
                    $filePaths = [];
                    foreach ($request->file($field['field']) as $index => $file) {
                        $fileName = $field['field'] . '/' . $instance['id'] . '_' . $index . '_' . $file->getClientOriginalName();
                        $filePath = $file->storeAs($storagePath, $fileName);
                        $this->encryptPrivateFileIfNeeded($filePath, $field);
                        $filePaths[] = $filePath;
                    }
                    $instance->{$field['field']} = json_encode($filePaths);
                } else {
                    $filePath = $request->file($field['field'])->storeAs($storagePath, $field['field'] . '/' . $instance['id'] . '_' . time());
                    $this->encryptPrivateFileIfNeeded($filePath, $field);
                    $instance->{$field['field']} = $filePath;
                }
            }
        }
    }

    protected function prepareFileUpdate($request, $model, $id, $instance, $field, array &$validatedData)
    {
        if (isset($field['multiple']) && $field['multiple']) {
            $existingFiles = json_decode($instance->{$field['field']}, true) ?: [];
            $filesToDelete = $request->input($field['field'] . '_delete', []);

            if (!empty($filesToDelete)) {
                foreach ($filesToDelete as $fileToDelete) {
                    Storage::delete($fileToDelete);
                    $existingFiles = array_filter($existingFiles, function ($file) use ($fileToDelete) {
                        return $file !== $fileToDelete;
                    });
                }
                $existingFiles = array_values($existingFiles);
            }

            if ($request->hasFile($field['field'])) {
                $storagePath = $this->storagePath($field, $model);

                foreach ($request->file($field['field']) as $file) {
                    $fileName = $field['field'] . '/' . $id . '_' . time() . '_' . $file->getClientOriginalName();
                    $filePath = $file->storeAs($storagePath, $fileName);
                    $this->encryptPrivateFileIfNeeded($filePath, $field);
                    $existingFiles[] = $filePath;
                }
            }

            $validatedData[$field['field']] = !empty($existingFiles) ? json_encode($existingFiles) : null;
            return;
        }

        if ($request->input($field['field'] . '_edited')) {
            if ($instance->{$field['field']}) {
                Storage::delete($instance->{$field['field']});
            }
            $validatedData[$field['field']] = null;
        }

        if ($request->hasFile($field['field'])) {
            $storagePath = $this->storagePath($field, $model);
            $filePath = $request->file($field['field'])->storeAs($storagePath, $field['field'] . '/' . $id . '_' . time());
            $this->encryptPrivateFileIfNeeded($filePath, $field);
            $validatedData[$field['field']] = $filePath;
        }
    }

    protected function storagePath($field, $model)
    {
        $storagePath = (isset($field['public']) && $field['public']) ? 'public/' : 'private/';
        $storagePath .= $field['type'] === 'image' ? 'images/' : 'files/';

        return $storagePath . $model;
    }

    protected function encryptPrivateFileIfNeeded($filePath, $field)
    {
        if ((!isset($field['public']) || !$field['public']) && $field['type'] === 'file') {
            $fileContent = Storage::get($filePath);
            $encryptedContent = Crypt::encryptString($fileContent);
            Storage::put($filePath, $encryptedContent);
        }
    }

    protected function withoutCustomFields(array $data)
    {
        return array_filter($data, function ($value, $key) {
            return strpos($key, 'custom_') !== 0;
        }, ARRAY_FILTER_USE_BOTH);
    }

    protected function requestDataWithoutUploadedFiles($request)
    {
        return array_filter($request->all(), function ($value) {
            return !($value instanceof \Illuminate\Http\UploadedFile);
        });
    }

    protected function addCustomFieldsToItem($item)
    {
        if ($item::hasCustomFieldsEnabled()) {
            $customValues = $item->getCustomFieldsValues();
            foreach ($customValues as $key => $value) {
                $item->setAttribute($key, $value);
            }
        }
    }
}
