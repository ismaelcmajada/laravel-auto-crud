<?php

namespace Ismaelcmajada\LaravelAutoCrud\Models\Traits;

use Illuminate\Support\Facades\Schema;
use Ismaelcmajada\LaravelAutoCrud\Casts\DateTimeWithUserTimezone;
use Ismaelcmajada\LaravelAutoCrud\Casts\DateWithUserTimezone;
use App\Models\Record;

trait AutoCrud
{


    protected $fillable = [];
    protected $casts = [];
    protected $hidden = [];

    protected function initializeAutoCrud()
    {

        $this->fillable = array_column(static::getFormFields(), 'field');

        foreach (static::getFields() as $field) {
            if ($field['type'] === 'number' && isset($field['relation'])) {
                $this->casts[$field['field']] = 'integer';
            } elseif ($field['type'] === 'number') {
                $this->casts[$field['field']] = 'string';
            } elseif ($field['type'] === 'boolean') {
                $this->casts[$field['field']] = 'boolean';
            } elseif ($field['type'] === 'password') {
                $this->casts[$field['field']] = 'hashed';
                $this->hidden[] = $field['field'];
            } elseif ($field['type'] === 'date') {
                $this->casts[$field['field']] = DateWithUserTimezone::class . ':d-m-Y';
            } elseif ($field['type'] === 'datetime') {
                $this->casts[$field['field']] = DateTimeWithUserTimezone::class . ':d-m-Y H:i';
            } else if ($field['type'] === 'telephone') {
                $this->casts[$field['field']] = 'string';
            }
        }
    }

    public static function getIncludes()
    {
        $totalIncludes = static::$includes;
        $totalIncludes[] = 'records.user';

        foreach (static::getFields() as $field) {
            if (isset($field['relation']) && !in_array($field['relation']['relation'], $totalIncludes)) {
                $totalIncludes[] = $field['relation']['relation'];
            }
        }

        foreach (static::$externalRelations as $relation) {
            if (!in_array($relation['relation'], $totalIncludes)) {
                $totalIncludes[] = $relation['relation'];
            }
        }

        return $totalIncludes;
    }

    public static function getEndpoint($model = null)
    {
        $modelName = lcfirst(
            str_replace('App\\Models\\', '', $model ?? static::class)
        );

        return "/laravel-auto-crud/{$modelName}";
    }

    public static function getModelName()
    {
        return lcfirst(
            str_replace('App\\Models\\', '', static::class)
        );
    }

    abstract protected static function getFields();

    public static function getCustomRules()
    {
        return [];
    }

    public static function getCustomForbiddenActions()
    {
        return [];
    }

    public static function getForbiddenActions()
    {
        return static::$forbiddenActions;
    }

    public static function getExternalRelations()
    {

        foreach (static::$externalRelations as &$relation) {

            $relation['endPoint'] = static::getEndpoint($relation['model']);

            if (isset($relation['pivotFields'])) {
                foreach ($relation['pivotFields'] as &$pivotField) {
                    if (isset($pivotField['relation'])) {
                        $pivotField['relation']['endPoint'] = static::getEndpoint($pivotField['relation']['model']);
                    }
                }
            }
        }

        return static::$externalRelations;
    }

    public static function getFormFields()
    {


        $formFields = array_filter(static::getFields(), function ($field) {
            return $field['form'];
        });


        foreach ($formFields as $key => $field) {
            if (isset($field['comboField'])) {
                $formFields[$field['comboField']] = [
                    'field' => $field['comboField'],
                    'type' => 'string',
                    'table' => false,
                    'form' => true,
                    'hidden' => true,
                    'rules' => [
                        'required' => true
                    ]
                ];
            }

            if (isset($field['relation']) && (!isset($field['relation']['polymorphic']) || !$field['relation']['polymorphic'])) {
                $formFields[$key]['relation']['endPoint'] =  static::getEndpoint($field['relation']['model']);
            }
        }

        return array_values($formFields);
    }

    public static function getTableFields()
    {
        $tableFields = array_filter(static::getFields(), function ($field) {
            return isset($field['table']) && $field['table'];
        });

        return array_values($tableFields);
    }

    public function __call($method, $parameters)
    {
        foreach (static::getFields() as $field) {
            if (isset($field['relation']) && isset($field['relation']['relation']) && $field['relation']['relation'] === $method) {
                if (isset($field['relation']['polymorphic']) && $field['relation']['polymorphic'] && $field['relation']['relation'] === $method) {
                    return $this->morphTo($field['relation']['relation'], $field['relation']['morphType'], $field['field']);
                } else {
                    return $this->handleRelation($field);
                }
            }
        }

        foreach (static::getExternalRelations() as $relation) {
            if (isset($relation['relation']) && $relation['relation'] === $method) {
                return $this->handleExternalRelation($relation);
            }
        }

        return parent::__call($method, $parameters);
    }

    protected function handleRelation($field)
    {
        $relatedModelClass = $field['relation']['model'];

        if (!class_exists($relatedModelClass)) {
            throw new \Exception("Modelo relacionado {$relatedModelClass} no existe");
        }

        $relation = $this->belongsTo($relatedModelClass, $field['field']);

        if ($this->usesSoftDeletes($relatedModelClass)) {
            $relation = $relation->withTrashed();
        }

        return $relation;
    }

    protected function handleExternalRelation($relation)
    {
        $relatedModelClass = $relation['model'];
        if (!class_exists($relatedModelClass)) {
            throw new \Exception("Modelo relacionado {$relatedModelClass} no existe");
        }

        $relatedPivotModelClass = $relation['pivotModel'] ?? null;
        if (class_exists($relatedPivotModelClass)) {
            $relationMethod = $this->belongsToMany($relatedModelClass, $relation['pivotTable'], $relation['foreignKey'], $relation['relatedKey'])->using($relatedPivotModelClass);
        } else {
            $relationMethod = $this->belongsToMany($relatedModelClass, $relation['pivotTable'], $relation['foreignKey'], $relation['relatedKey']);
        }

        if ($this->usesSoftDeletes($relatedModelClass)) {
            $relationMethod = $relationMethod->withTrashed();
        }

        if (isset($relation['pivotFields'])) {
            $tableFields = Schema::getColumnListing($relation['pivotTable']);

            $relationMethod->withPivot($tableFields);
        }

        return $relationMethod;
    }

    public static function getModel($processedModels = [])
    {

        $forbiddenActions = static::getForbiddenActions();

        foreach ($forbiddenActions as $role => $actions) {
            if (isset($actions['custom'])) {
                unset($forbiddenActions[$role]['custom']);
            }
        }

        return [
            'endPoint' => static::getEndpoint(),
            'formFields' => static::getFormFields($processedModels),
            'tableHeaders' => static::getTableHeaders(),
            'externalRelations' => static::getExternalRelations($processedModels),
            'forbiddenActions' => $forbiddenActions,
            'calendarFields' => static::getCalendarFields(),
        ];
    }

    protected static function getTableHeaders()
    {
        $headers = array_map(function ($field) {

            if (isset($field['relation'])) {
                if (!isset($field['relation']['polymorphic']) || !$field['relation']['polymorphic']) {
                    return [
                        'title' => $field['name'],
                        'sortable' => true,
                        'key' => $field['field'],
                        'relation' => $field['relation'],
                        'align' => 'center',
                    ];
                }
            }
            return [
                'title' => $field['name'],
                'sortable' => true,
                'key' => $field['field'],
                'align' => 'center',
                'type' => $field['type'],
            ];
        }, static::getTableFields());

        foreach (static::getExternalRelations() as $externalRelation) {
            if (isset($externalRelation['table']) && $externalRelation['table']) {
                $headers[] = [
                    'title' => $externalRelation['name'],
                    'key' => $externalRelation['relation'],
                    'sortable' => false,
                    'searchable' => false,
                    'align' => 'center',
                ];
            }
        }

        $headers[] = [
            'title' => 'Acciones',
            'key' => 'actions',
            'sortable' => false,
            'align' => 'center',
        ];

        return $headers;
    }

    public static function getTableKeyFields()
    {
        $tableKeyFields = [];

        foreach (static::getFields() as $field) {
            if (isset($field['relation']) && isset($field['relation']['tableKey'])) {
                $tableKey = $field['relation']['tableKey'];
                preg_match_all('/\{([\w\.]+)\}/', $tableKey, $matches);
                $fields = $matches[1];
                $tableKeyFields[$field['field']] = [
                    'relation' => $field['relation']['relation'],
                    'fields' => $fields,
                    'literals' => preg_split('/\{[\w\.]+\}/', $tableKey),
                    'tableKey' => $tableKey
                ];
            }
        }

        return $tableKeyFields;
    }

    public static function getFormKeyFields()
    {
        $formKeyFields = [];

        foreach (static::getFields() as $field) {
            if (isset($field['relation']) && isset($field['relation']['formKey'])) {
                $formKey = $field['relation']['formKey'];
                preg_match_all('/\{(\w+)\}/', $formKey, $matches);
                $fields = $matches[1];
                $formKeyFields[$field['field']] = [
                    'relation' => $field['relation']['relation'],
                    'fields' => $fields,
                    'literals' => preg_split('/\{\w+\}/', $formKey),
                    'formKey' => $formKey
                ];
            }
        }

        return $formKeyFields;
    }

    protected static function getCalendarFields()
    {
        return static::$calendarFields;
    }


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->handleEvent('creating');
        });

        static::created(function ($model) {
            $model->handleEvent('created');
        });

        static::updating(function ($model) {
            $model->handleEvent('updating');
        });

        static::updated(function ($model) {
            $model->handleEvent('updated');
        });

        static::deleting(function ($model) {
            $model->handleEvent('deleting');
        });

        static::deleted(function ($model) {
            $model->handleEvent('deleted');
        });

        static::saving(function ($model) {
            $model->handleEvent('saving');
        });

        static::saved(function ($model) {
            $model->handleEvent('saved');
        });
    }

    protected function usesSoftDeletes($modelClass)
    {
        return in_array(
            'Illuminate\Database\Eloquent\SoftDeletes',
            class_uses_recursive($modelClass)
        );
    }

    protected function handleEvent($event)
    {
        if (method_exists($this, $event . 'Event')) {
            call_user_func([$this, $event . 'Event']);
        }
    }

    public function records()
    {
        return $this->morphMany(Record::class, 'recordable', 'model', 'element_id');
    }
}
