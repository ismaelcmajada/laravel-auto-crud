<?php

namespace Ismaelcmajada\LaravelAutoCrud\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CustomFieldValue extends Model
{
    protected $fillable = [
        'custom_field_definition_id',
        'model_type',
        'model_id',
        'value',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'custom_field_definition_id');
    }

    public function model(): MorphTo
    {
        return $this->morphTo('model', 'model_type', 'model_id');
    }

    public function getCastedValueAttribute()
    {
        if (!$this->definition) {
            return $this->value;
        }

        switch ($this->definition->type) {
            case 'number':
                return is_numeric($this->value) ? (float) $this->value : null;
            case 'boolean':
                return $this->value === '1' || $this->value === 1 || $this->value === true;
            case 'date':
            case 'datetime':
                return $this->value;
            default:
                return $this->value;
        }
    }

    public static function getValuesForModel(string $modelType, int $modelId): array
    {
        $values = static::where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->with('definition')
            ->get();

        $result = [];
        foreach ($values as $value) {
            if ($value->definition) {
                $result['custom_' . $value->definition->name] = $value->casted_value;
            }
        }

        return $result;
    }

    public static function setValuesForModel(string $modelType, int $modelId, array $customFieldsData): void
    {
        $definitions = CustomFieldDefinition::getFieldsForModel($modelType);

        foreach ($definitions as $definition) {
            $fieldKey = 'custom_' . $definition->name;

            if (array_key_exists($fieldKey, $customFieldsData)) {
                static::updateOrCreate(
                    [
                        'custom_field_definition_id' => $definition->id,
                        'model_type' => $modelType,
                        'model_id' => $modelId,
                    ],
                    [
                        'value' => self::prepareValueForStorage($customFieldsData[$fieldKey], $definition->type),
                    ]
                );
            }
        }
    }

    protected static function prepareValueForStorage($value, string $type): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        switch ($type) {
            case 'boolean':
                return $value ? '1' : '0';
            default:
                return (string) $value;
        }
    }
}
