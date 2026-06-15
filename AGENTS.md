# AGENTS.md

This repo is the **source of the `ismaelcmajada/laravel-auto-crud` Composer package**, not a Laravel application. The package auto-generates CRUD pipelines (web routes, Inertia/Vue/Vuetify screens, optional JSON API) for any Eloquent model that uses the `AutoCrud` trait.

## Repo shape

- `src/AutoCrudServiceProvider.php` — main provider: loads web/api routes, publishes config, JS assets, migrations, and the agent skill; registers middleware aliases `checkForbiddenActions` and `forceJsonResponse`.
- `src/ModelScanServiceProvider.php` — discovers models under `app_path('Models')` that use the `AutoCrud` trait and registers them as the `app('models')` singleton.
- `src/Console/Commands/AiContextCommand.php` — implements `php artisan ai:context`. Run inside a host Laravel app, not this repo. It writes a generated block into `AGENTS.md` between the markers `<!-- LARAVEL_AUTO_CRUD_AI_CONTEXT_START -->` and `<!-- LARAVEL_AUTO_CRUD_AI_CONTEXT_END -->`.
- `src/Models/Traits/AutoCrud.php` — the trait host models consume. Declares `getFields()` as `abstract`.
- `src/Http/Controllers/` — `AutoCrudController`, `AutoTableController`, `AutoCompleteController`, `CalendarController`, `CustomFieldDefinitionController`, `ImageController`, `FileController`, `SessionController`, `ApiAutoCrudController`, `ApiCustomFieldDefinitionController`.
- `routes/web.php`, `routes/api.php` — only loaded when the corresponding `laravel-auto-crud.{web|api}.enabled` config flag is true. `web` is on by default; `api` is off.
- `config/laravel-auto-crud.php` — published to the host. Defaults: web prefix `laravel-auto-crud`; api prefix `api/laravel-auto-crud`; timezone `Atlantic/Canary`.
- `resources/js/{Components,Utils,Composables,Adapters}/LaravelAutoCrud/` — Vue 3 sources published via `vendor:publish` tag `laravel-auto-crud`. No bundler runs in this repo; the host builds them.
- `skills/laravel-auto-crud/SKILL.md` (+ `references/`) — published as tag `laravel-auto-crud-skill` (to `.opencode/skills/laravel-auto-crud/`) and `laravel-auto-crud-skill-claude` (to `.claude/skills/`).
- `database/migrations/create_custom_fields_tables.php.stub` — published as tag `laravel-auto-crud-migrations` with a fresh timestamp each run.
- `docs/` — package docs (`fields.md`, `relationships.md`, `validation.md`, `hooks.md`, `custom-search.md`, `examples.md`, `api.md`, `api-mode.md`, `ai-context.md`, `frontend/...`).

## What is not in this repo

- No `tests/` directory, no PHPUnit/Pest config — do not look for or invent a test runner.
- No `.github/workflows/`, no CI config.
- No `package.json`, no JS build step, no `node_modules`. The shipped `resources/js/**` is hand-written Vue 3 SFC sources; the host app is expected to bundle them.
- No `.env.example`, no Docker, no host-app scaffolding.

## Useful commands

- `composer install` — install dev deps (illuminate packages, doctrine, etc. in `vendor/`).
- `composer validate` — sanity-check `composer.json` (the autoload PSR-4 mapping is `Ismaelcmajada\LaravelAutoCrud\` → `src/`).
- `php -l <file>` — lint a single PHP file. There is no project-wide PHP-CS-Fixer or Pint config.
- `php artisan ai:context` — package feature, not a dev command for this repo. Runs in a host Laravel app that has installed the package.

There is no `lint`, `test`, or `typecheck` script defined. After any change, at minimum run `php -l` over touched files and `composer validate`.

## Quirks a future agent will trip on

- **PHP 7.2+ minimum, Laravel 7–13 supported.** Do not use PHP 8.0+ syntax (no constructor property promotion, no `match`, no `enum`, no `readonly`, no `mixed` type, no named arguments in calls, no nullsafe `?->`). Use array syntax for old-style arrays where consistency matters.
- **Two service providers are auto-discovered** via `composer.json` `extra.laravel.providers`: `AutoCrudServiceProvider` and `ModelScanServiceProvider`. Anything that needs to run at boot belongs in one of them — do not add a third.
- **Published assets are read-only for downstream users.** Code under `resources/js/**/LaravelAutoCrud/`, the stubbed custom-fields migration, and the published config are meant to be regenerated; behaviour changes belong in PHP, not in the published JS.
- **`ModelScanServiceProvider` only scans `app_path('Models')`.** It does **not** follow subdirectories across non-standard model paths. The trait lookup uses `class_uses_recursive(...)['Ismaelcmamada\\LaravelAutoCrud\\Models\\Traits\\AutoCrud']` — a missing trait means the model is silently skipped.
- **Routes must be loaded via the existing `loadRoutesFrom` calls**, guarded by the `web.enabled` / `api.enabled` config flags. Do not add a routes file at the package root.
- **Middleware aliases** (`checkForbiddenActions`, `forceJsonResponse`) are registered in `registerMiddleware()` inside `AutoCrudServiceProvider::boot()`. Add new aliases there, not in `register()`.
- **Frontend adapter contract** — host apps must call `app.use(createAutoCrudPlugin({ adapter: createInertiaAutoCrudAdapter() }))` once. The Inertia adapter is the default; the API/external-frontend variant is `createApiAutoCrudAdapter({ baseUrl, axios })`. Any new adapter has to implement the same surface that `resources/js/Adapters/LaravelAutoCrud` exposes.
- **Payload key casing is fixed.** Generated/serialized keys use `endPoint` (capital `P`), `itemTitle`, `tableKey`, `formKey`, `comboField`, `morphType`, `pivotTable`, `pivotFields`, `foreignKey`, `relatedKey`, `customFieldsEnabled`. Changing case is a breaking API change.
- **The `ai:context` block in a host `AGENTS.md` is regenerable.** Anything inside the `LARAVEL_AUTO_CRUD_AI_CONTEXT_*` markers will be overwritten by the command. Manual content goes outside the markers.

## Editing the `AutoCrud` trait or model contract

- The trait references `static::$includes`, `static::$externalRelations`, `static::$forbiddenActions`, `static::$calendarFields`, and `static::$customFieldsEnabled` directly. Host models must declare them as `protected static` properties, even as `[]`, or the trait will error.
- `getFields()` is `abstract static`. Every model must implement it.
- The `combobox` field type is intentionally string-typed in DB (no FK); it carries `endPoint` + `itemTitle` and **never** a `relation` key. The `relation` key is reserved for true `belongsTo` / `morphTo` fields.
- `select` field `options` is a flat array of strings; associative arrays and arrays of `{value,label}` objects are not supported.
