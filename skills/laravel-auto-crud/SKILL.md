---
name: laravel-auto-crud
description: Use this skill whenever you need to add, modify, or scaffold a CRUD in a Laravel + Inertia + Vue 3 + Vuetify 3 project that uses the `ismaelcmajada/laravel-auto-crud` package. Triggers include defining a new Eloquent model with the `AutoCrud` trait, configuring `getFields()`, declaring relationships (BelongsTo, MorphTo, HasMany, BelongsToMany), exposing a model through Inertia (`HandleInertiaRequests`), rendering an `AutoTable` / `AutoForm` / `AutoFormDialog`, adding custom validation rules, lifecycle event hooks (`creatingEvent`, `updatedEvent`, …), `scopeSearchXxx` search scopes, image/file fields, polymorphic relations, calendar configuration, or `$forbiddenActions`. Use it BEFORE writing migrations, controllers, requests, routes or Vue pages for any model that should expose an automated CRUD.
---

# laravel-auto-crud

Skill for working with the `ismaelcmajada/laravel-auto-crud` package. The package autogenerates the entire CRUD pipeline (routes, controllers, FormRequests, validation, table/form payloads, autocomplete endpoints, file/image handling, calendar events, soft-delete management, history records, pivot management, etc.) from a single model that uses the `AutoCrud` trait. Most of the plumbing is already done — your job is almost always limited to:

1. Writing/adjusting the migration.
2. Writing the model with `getFields()`, optional `$externalRelations`, `$includes`, hooks, custom rules and search scopes.
3. Sharing the model in `HandleInertiaRequests::share()`.
4. Creating the Inertia page that renders `<auto-table>` (and/or `<auto-form-dialog>`).

Everything else is handled by the package.

---

## ⚠️ HARD RULES — read first

### 1. Published assets are READ-ONLY

When the user runs:

```bash
php artisan vendor:publish --tag=laravel-auto-crud --force
```

…the package copies these directories into the host project:

- `resources/js/Components/LaravelAutoCrud/` (AutoTable.vue, AutoForm.vue, AutoFormDialog.vue, AutoCalendar.vue, AutocompleteServer.vue, AutoExternalRelation.vue, CustomFieldsManager.vue, DestroyDialog.vue, DestroyPermanentDialog.vue, ExpandableList.vue, ExpandableText.vue, HistoryDialog.vue, ImageDialog.vue, LoadingOverlay.vue, RestoreDialog.vue, VDatetimePicker.vue)
- `resources/js/Utils/LaravelAutoCrud/` (arrays.js, autocompleteUtils.js, datatableUtils.js, dates.js, excel.js, rules.js, url.js)
- `resources/js/Composables/LaravelAutoCrud/` (useDialogs.js, useTableServer.js)

**You MUST NEVER edit, refactor, "improve", reformat or patch any file inside those three folders.** They are the package's distribution artefacts; any local change will be lost the next time the user re-publishes with `--force` and any bug-fix you add there is undefined behaviour. The same applies to:

- The published config file `config/laravel-auto-crud.php`.
- The published migration `..._create_custom_fields_tables.php`.

If you genuinely need different behaviour, **wrap or extend** from outside (custom Vue page that imports the published component, custom slot, custom prop, custom field rendering via the `#field.<name>` slot, custom controller, etc.) — do NOT modify the published source.

### 2. `select` field options: FLAT array of strings — NEVER associative

For any field with `'type' => 'select'`, the `options` key **must be a plain (numerically indexed) array of strings**:

```php
// ✅ CORRECT
[
    'name'    => 'Status',
    'field'   => 'status',
    'type'    => 'select',
    'options' => ['pending', 'confirmed', 'cancelled'],
    'form'    => true,
    'table'   => true,
],
```

```php
// ❌ WRONG — associative arrays are NOT supported
'options' => ['pending' => 'Pending', 'confirmed' => 'Confirmed'],

// ❌ WRONG — arrays of objects/labels are NOT supported
'options' => [['value' => 'pending', 'label' => 'Pending']],
```

The string stored in the database **is** the value shown to the user. If the user wants a different label, that label must be the value itself (translate at the rendering layer via a custom slot if needed). Some examples in the published `docs/examples.md` show associative arrays — **those examples are wrong; ignore them and follow this rule.**

---

## Things the package already does — DO NOT re-implement or "verify"

When working on a model that uses `AutoCrud`, do **not** add the following (it is wasted work and often breaks the magic):

| The package handles automatically                                                                                                                                                                                                                                                                                                             | Don't do this                                                                                                                                                                                                                |
| --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------- | --------------------------------------------------- |
| **Auto-discovers every model in `app/Models/**`that uses the`AutoCrud`trait** via`ModelScanServiceProvider`and binds them as the`models`container singleton — an array keyed by`Str::lower(class_basename($model))`whose value is`Model::getModel()` (the full payload: fields, rules, relationships, endpoints, table headers, form fields). | Don't manually call `Foo::getModelConfig()` / `Foo::getModel()` per model — just inject `app('models')` (or resolve `'models'`) and pass it to Inertia. Adding a new model to `app/Models/` is enough; no registration code. |
| Fills `$fillable` from `getFields()`                                                                                                                                                                                                                                                                                                          | Don't declare `$fillable` for fields already in `getFields()`                                                                                                                                                                |
| Builds `$casts` from each field's `type` (`boolean`, `date`, `datetime`, `password→hashed`, etc.)                                                                                                                                                                                                                                             | Don't add casts for those fields                                                                                                                                                                                             |
| Adds `password` fields to `$hidden`                                                                                                                                                                                                                                                                                                           | Don't add them manually                                                                                                                                                                                                      |
| Registers all CRUD routes under `/laravel-auto-crud/{model}/...` (index/store/update/destroy/restore/permanent/export-excel/getItem/all/load-items/load-autocomplete-items/load-calendar-events/pivot/bind/unbind)                                                                                                                            | Don't write resource routes, controllers or `Route::apiResource` for the model                                                                                                                                               |
| Provides controllers (`AutoCrudController`, `AutoTableController`, `AutoCompleteController`, `CalendarController`, `ImageController`, `FileController`, `CustomFieldDefinitionController`, `SessionController`)                                                                                                                               | Don't create your own CRUD controller for the model                                                                                                                                                                          |
| Generates a `DynamicFormRequest` with the rules built from each field's `rules` + `getCustomRules()`                                                                                                                                                                                                                                          | Don't create a FormRequest for the model                                                                                                                                                                                     |
| Resolves relationships dynamically via `__call` from each field's `relation` config and from `$externalRelations`                                                                                                                                                                                                                             | Don't write `belongsTo` / `morphTo` / `belongsToMany` methods for relationships already declared in `getFields()` or `$externalRelations`                                                                                    |
| Applies `withTrashed()` to related models that use `SoftDeletes`                                                                                                                                                                                                                                                                              | Don't add `->withTrashed()` manually                                                                                                                                                                                         |
| Applies `withPivot([...])` from `pivotFields`                                                                                                                                                                                                                                                                                                 | Don't add it manually                                                                                                                                                                                                        |
| Generates and exposes endpoints (`/laravel-auto-crud/{model}/...`) for every model and pivot                                                                                                                                                                                                                                                  | Don't hard-code endpoints in Vue — read them from `model.endpoint` (already in the payload)                                                                                                                                  |
| Builds `tableHeaders`, `formFields`, `tableKey` / `formKey` resolution                                                                                                                                                                                                                                                                        | Don't compute headers manually in the page                                                                                                                                                                                   |
| Stores public/private images and files (encrypts private files with `Crypt`) under `storage/{public                                                                                                                                                                                                                                           | private}/{images                                                                                                                                                                                                             | files}/{model}/{field}/{id}` | Don't handle file uploads or storage paths yourself |
| Records history (morphMany `records`) and exposes `HistoryDialog`                                                                                                                                                                                                                                                                             | Don't build an audit trail for the model                                                                                                                                                                                     |
| Honours `$forbiddenActions` per role through the `checkForbiddenActions` middleware                                                                                                                                                                                                                                                           | Don't gate destroy/restore actions in your own code                                                                                                                                                                          |
| Soft-delete-aware destroy / `destroyPermanent` / `restore` flows + UI dialogs                                                                                                                                                                                                                                                                 | Don't write delete/restore handlers                                                                                                                                                                                          |
| Excel export endpoint and button                                                                                                                                                                                                                                                                                                              | Don't implement export                                                                                                                                                                                                       |
| Inertia flash for `data` / `message` after each action                                                                                                                                                                                                                                                                                        | Don't manually emit success toasts after CRUD actions                                                                                                                                                                        |
| Server-side pagination, sorting, multi-column filtering                                                                                                                                                                                                                                                                                       | Don't paginate manually                                                                                                                                                                                                      |

If the user asks you to "create a controller / FormRequest / route for model X that uses AutoCrud" — push back briefly: it is not needed. Only create those if the requirement is something explicitly outside the package (e.g. a non-CRUD action, a webhook, a public API endpoint, etc.).

---

## Required workflow for adding a new CRUD

1. **Migration** — only thing the package cannot infer. Add the columns matching every `field` in `getFields()` (plus `softDeletes()` if you'll use them, plus the foreign keys for `belongsTo`/`morphs`, plus pivot tables for `BelongsToMany`).

2. **Model** — `use AutoCrud, SoftDeletes;` and implement at minimum `protected static function getFields(): array`. Add `$externalRelations`, `$includes`, `$forbiddenActions`, `$calendarFields`, hooks, `getCustomRules()` and `scopeSearchXxx` only if needed.

3. **Inertia share** — share the auto-discovered `models` singleton (the package's `ModelScanServiceProvider` already scans `app/Models/**` and registers every model that uses the `AutoCrud` trait). You do **not** need to list models one by one — just expose the whole bag plus the flash payload in `HandleInertiaRequests::share()`:

   ```php
   public function share(Request $request): array
   {
       return array_merge(parent::share($request), [
           'models' => app('models'),       // auto-discovered, keyed by lowercase class basename
           'flash'  => [
               'data'    => fn () => $request->session()->get('data'),
               'message' => fn () => $request->session()->get('message'),
           ],
       ]);
   }
   ```

   Adding a new `App\Models\Foo` that uses `AutoCrud` automatically makes `page.props.models.foo` available on the frontend — no code change in the share method, no manual `Foo::getModelConfig()` call.

4. **Page route** — a single Inertia render route, e.g. `Route::get('/products', fn () => Inertia::render('Products'))->name('products');`. Do NOT add CRUD verbs.

5. **Vue page** — render the published component with the model from props:

   ```vue
   <script setup>
   import AutoTable from "@/Components/LaravelAutoCrud/AutoTable.vue"
   import { usePage } from "@inertiajs/vue3"
   const model = usePage().props.models.product
   </script>
   <template>
     <auto-table title="Products" :model="model" />
   </template>
   ```

---

## Field definition reference (concise)

Every entry in `getFields()` is an associative array. Required keys: `name`, `field`, `type`. Common keys: `table`, `form`, `rules`, `default`, `onlyUpdate`, `hidden`, `options`, `endPoint`, `itemTitle`, `comboField`, `relation`, `public`.

**Types:** `string`, `number`, `decimal`, `boolean`, `password`, `text`, `telephone`, `date`, `datetime`, `select`, `combobox`, `image`, `file`.

**Type-specific notes:**

- `select` → requires `options` as a **flat array of strings** (see hard rule above).
- `combobox` → requires `endPoint` (string, the autocomplete endpoint, normally `model.endpoint` of another AutoCrud model) and `itemTitle` (the field of the related model to display).
- `image` / `file` → optional `public` (default `true`). Private files are encrypted automatically.
- `password` → automatically hashed and hidden.
- `date` / `datetime` → cast with user-timezone casts automatically.

**Validation in `rules`:** `'required' => true`, `'unique' => true`, `'custom' => ['rule_name', ...]` (each name maps to a closure in `getCustomRules()` with signature `function ($attribute, $value, $fail, $request)`; access full payload through `$request->getData()`).

**BelongsTo / MorphTo** — declare via the field's `relation` key (don't write the Eloquent method):

```php
'relation' => [
    'model'    => User::class,    // omit / null for morphTo
    'relation' => 'user',
    'tableKey' => '{name} ({email})',
    'formKey'  => '{name}',
    // morphTo only:
    'polymorphic' => true,
    'morphType'   => 'commentable_type',
],
```

**HasMany / BelongsToMany** — declare via `protected static $externalRelations = [...]`. For `hasMany` set `'type' => 'hasMany'` and `foreignKey`. For `belongsToMany` provide `pivotTable`, `foreignKey`, `relatedKey`, optional `pivotModel`, optional `pivotFields` (which themselves follow the same field-definition shape).

---

## Frontend usage reminders

- Only **import** the published components from `@/Components/LaravelAutoCrud/...` — never edit them.
- Customise behaviour through **props and slots** (the components expose a rich slot tree: `#table.actions.prepend`, `#table.actions`, `#table.actions.append`, `#item.<columnKey>`, `#item.actions(.prepend|.append)`, `#auto-form-dialog.auto-form.field.<fieldName>`, `#auto-form-dialog.auto-form.prepend|append|after-save`, `#auto-external-relation.<relation>.actions`, etc.). When the user wants a "custom column" or "custom field rendering", use a slot — do not patch the component.
- The model object (`page.props.models.<name>`) already contains `endpoint`, `tableHeaders`, `formFields`, `rules`, relationships and all metadata. Read from it; don't reconstruct.
- For pages that only need a dialog, import `AutoFormDialog.vue` and pass either `:model="model"` or `model-name="App\\Models\\Foo"`.

---

## Decision checklist when the user asks for "a CRUD for X"

1. Does X need a database table? → write the migration.
2. Does the model exist? → create `App\Models\X` with `use AutoCrud[, SoftDeletes];` and `getFields()`.
3. Are there foreign keys? → put them in `getFields()` with the `relation` key (don't write the relationship method).
4. Are there many-to-many or hasMany? → use `$externalRelations`.
5. Are there cross-field validations? → `getCustomRules()` + `'custom' => [...]` in the field's rules.
6. Are there side effects (emails, audit, derived columns)? → lifecycle hooks (`creatingEvent`, `updatedEvent`, …).
7. Does the user want extra search filters? → `scopeSearchXxx(Builder $query, $value): Builder`.
8. Share the model in `HandleInertiaRequests`.
9. Add the Inertia page route + Vue page with `<auto-table>`.
10. **Stop.** Do not add controllers, FormRequests, CRUD routes, casts already covered by field types, `$fillable` for AutoCrud-managed fields, manual relationship methods for relations already declared, file-upload handlers, audit logging, soft-delete UI, export, or pagination logic.

---

## Quick anti-patterns to reject

- "Edit `AutoTable.vue` to add a column." → Use `customHeaders` prop + `#item.<key>` slot.
- "Override the published controller." → Don't. Use a model hook or a custom search scope.
- "Add `protected $fillable = [...]` for AutoCrud fields." → Remove it.
- "Write a `belongsTo` method for a field that already declares `relation`." → Remove it.
- "`'options' => ['a' => 'A', 'b' => 'B']` for a select." → Replace with `['a', 'b']` (or with the user-facing labels themselves).
- "Add a route `Route::resource('products', ...)`." → Remove it; the package already exposes `/laravel-auto-crud/products/...`.
- "Add `->withTrashed()` to a relationship." → Remove it; the trait does it when the related model uses `SoftDeletes`.
