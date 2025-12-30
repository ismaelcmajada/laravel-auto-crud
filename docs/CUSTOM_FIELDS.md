# Sistema de Campos Personalizados (Custom Fields)

El sistema de Custom Fields permite agregar campos dinámicos a cualquier modelo que use el trait `AutoCrud`, sin necesidad de modificar las migraciones del modelo original.

## Instalación

### 1. Publicar las migraciones

```bash
php artisan vendor:publish --tag=laravel-auto-crud-migrations
php artisan migrate
```

Esto creará dos tablas:

- `custom_field_definitions`: Almacena las definiciones de campos personalizados
- `custom_field_values`: Almacena los valores de los campos para cada instancia

### 2. Habilitar Custom Fields en un modelo

En tu modelo, define la propiedad estática `$customFieldsEnabled`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Ismaelcmajada\LaravelAutoCrud\Models\Traits\AutoCrud;

class Product extends Model
{
    use AutoCrud;

    // Habilitar custom fields para este modelo
    protected static bool $customFieldsEnabled = true;

    protected static $includes = [];
    protected static $externalRelations = [];
    protected static $forbiddenActions = [];
    protected static $calendarFields = [];

    protected static function getFields()
    {
        return [
            [
                'field' => 'name',
                'name' => 'Nombre',
                'type' => 'string',
                'table' => true,
                'form' => true,
                'rules' => ['required' => true]
            ],
            // ... otros campos
        ];
    }
}
```

## Uso

### Tipos de campos soportados

| Tipo       | Descripción                    |
| ---------- | ------------------------------ |
| `string`   | Texto corto (input text)       |
| `number`   | Número (input number)          |
| `text`     | Texto largo (textarea)         |
| `boolean`  | Checkbox (sí/no)               |
| `date`     | Selector de fecha              |
| `datetime` | Selector de fecha y hora       |
| `select`   | Lista desplegable con opciones |

### API Endpoints

#### Listar campos personalizados de un modelo

```
GET /laravel-auto-crud/custom-fields/{model}
```

#### Crear un campo personalizado

```
POST /laravel-auto-crud/custom-fields/{model}
```

Body:

```json
{
  "label": "Color favorito",
  "type": "select",
  "options": ["Rojo", "Verde", "Azul"],
  "rules": { "required": true },
  "is_active": true,
  "show_in_table": false
}
```

#### Actualizar un campo personalizado

```
PUT /laravel-auto-crud/custom-fields/{model}/{id}
```

#### Eliminar un campo personalizado

```
DELETE /laravel-auto-crud/custom-fields/{model}/{id}
```

#### Obtener tipos de campos disponibles

```
GET /laravel-auto-crud/custom-fields-types
```

### Componente Vue: CustomFieldsManager

Usa el componente `CustomFieldsManager` para gestionar los campos desde la interfaz:

```vue
<template>
  <CustomFieldsManager model-name="product" @updated="onFieldsUpdated" />
</template>

<script setup>
import CustomFieldsManager from "@/Components/LaravelAutoCrud/CustomFieldsManager.vue"

const onFieldsUpdated = () => {
  // Recargar el modelo si es necesario
}
</script>
```

### Acceso programático a valores

```php
// Obtener todos los valores custom de una instancia
$product = Product::find(1);
$customValues = $product->getCustomFieldsValues();
// Retorna: ['custom_color' => 'Rojo', 'custom_talla' => 'M']

// Obtener un valor específico
$color = $product->getCustomFieldValue('color');

// Establecer un valor
$product->setCustomFieldValue('color', 'Verde');

// Obtener modelo con custom fields incluidos
$data = $product->toArrayWithCustomFields();
```

## Estructura de la base de datos

### custom_field_definitions

| Campo         | Tipo   | Descripción                               |
| ------------- | ------ | ----------------------------------------- |
| id            | bigint | ID único                                  |
| model_type    | string | Clase del modelo (ej: App\Models\Product) |
| name          | string | Nombre único del campo (slug)             |
| label         | string | Etiqueta visible en UI                    |
| type          | string | Tipo de campo                             |
| options       | json   | Opciones para selects                     |
| rules         | json   | Reglas de validación                      |
| order         | int    | Orden de aparición                        |
| is_active     | bool   | Si el campo está activo                   |
| show_in_table | bool   | Si se muestra en la tabla                 |

### custom_field_values

| Campo                      | Tipo   | Descripción        |
| -------------------------- | ------ | ------------------ |
| id                         | bigint | ID único           |
| custom_field_definition_id | bigint | FK a definición    |
| model_type                 | string | Clase del modelo   |
| model_id                   | bigint | ID de la instancia |
| value                      | text   | Valor almacenado   |

## Notas importantes

1. **Prefijo `custom_`**: Todos los campos personalizados usan el prefijo `custom_` para evitar conflictos con campos del modelo. Por ejemplo, un campo con nombre `color` se accede como `custom_color`.

2. **Opcional por modelo**: El sistema es completamente opcional. Solo los modelos con `$customFieldsEnabled = true` tendrán soporte para campos personalizados.

3. **Sin migraciones adicionales**: Los campos personalizados no requieren modificar las migraciones del modelo original.

4. **Validación**: Las reglas de validación se aplican automáticamente en el formulario.

5. **Eliminación en cascada**: Al eliminar una definición de campo, todos sus valores asociados se eliminan automáticamente.
