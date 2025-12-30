<?php

namespace Ismaelcmajada\LaravelAutoCrud\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomFieldDefinition extends Model
{
    protected $fillable = [
        'model_type',
        'name',
        'label',
        'type',
        'options',
        'rules',
        'order',
        'is_active',
        'show_in_table',
    ];

    protected $casts = [
        'options' => 'array',
        'rules' => 'array',
        'is_active' => 'boolean',
        'show_in_table' => 'boolean',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    public static function getFieldsForModel(string $modelType): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('model_type', $modelType)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();
    }

    public function toFormField(): array
    {
        $field = [
            'field' => 'custom_' . $this->name,
            'name' => $this->label,
            'type' => $this->type,
            'table' => $this->show_in_table,
            'form' => true,
            'rules' => $this->rules ?? [],
            'isCustomField' => true,
            'customFieldId' => $this->id,
        ];

        if ($this->type === 'select' && !empty($this->options)) {
            $field['options'] = $this->options;
        }

        return $field;
    }
}
