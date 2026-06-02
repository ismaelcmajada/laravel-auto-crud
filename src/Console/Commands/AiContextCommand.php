<?php

namespace Ismaelcmajada\LaravelAutoCrud\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Ismaelcmajada\LaravelAutoCrud\Models\Traits\AutoCrud;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

class AiContextCommand extends Command
{
    protected $signature = 'ai:context {--path=AGENTS.md : Archivo relativo al proyecto que se actualizará} {--stdout : Imprime la sección sin escribir el archivo}';

    protected $description = 'Genera contexto del proyecto para IA y lo inserta en AGENTS.md sin tocar el resto del contenido.';

    protected $startMarker = '<!-- LARAVEL_AUTO_CRUD_AI_CONTEXT_START -->';

    protected $endMarker = '<!-- LARAVEL_AUTO_CRUD_AI_CONTEXT_END -->';

    public function handle()
    {
        $section = $this->buildSection();

        if ($this->option('stdout')) {
            $this->line($section);
            return 0;
        }

        $path = base_path($this->option('path') ?: 'AGENTS.md');
        $this->writeSection($path, $section);

        $this->info('Contexto de IA actualizado en ' . $path);

        return 0;
    }

    protected function buildSection()
    {
        $lines = [
            $this->startMarker,
            '# Laravel Auto Crud AI Context',
            '',
            '> Sección generada automáticamente por `php artisan ai:context`. Edita otras partes de `AGENTS.md`; este bloque se reemplaza al regenerar.',
            '',
            'Generado: ' . date('Y-m-d H:i:s'),
            '',
        ];

        $lines = array_merge($lines, $this->renderModels($this->discoverModels()));
        $lines = array_merge($lines, $this->renderDatabase($this->inspectDatabase()));
        $lines = array_merge($lines, $this->renderInertiaPages($this->discoverInertiaPages()));
        $lines = array_merge($lines, $this->renderVueComponents($this->discoverVueFiles()));

        $lines[] = $this->endMarker;

        return rtrim(implode("\n", $lines)) . "\n";
    }

    protected function writeSection($path, $section)
    {
        $directory = dirname($path);

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $current = File::exists($path) ? File::get($path) : '';
        $pattern = '/' . preg_quote($this->startMarker, '/') . '.*?' . preg_quote($this->endMarker, '/') . "\s*/s";

        if (preg_match($pattern, $current)) {
            $updated = preg_replace($pattern, rtrim($section) . "\n", $current);
        } else {
            $updated = rtrim($current);
            $updated .= ($updated === '' ? '' : "\n\n") . $section;
        }

        File::put($path, $updated);
    }

    protected function discoverModels()
    {
        $modelsPath = app_path('Models');

        if (!File::isDirectory($modelsPath)) {
            return [];
        }

        $models = [];

        foreach (File::allFiles($modelsPath) as $file) {
            $class = $this->classFromPhpFile($file->getPathname());

            if (!$class || !class_exists($class)) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($class);

                if (!$reflection->isSubclassOf(Model::class) || $reflection->isAbstract()) {
                    continue;
                }

                $instance = $reflection->newInstance();
                $usesAutoCrud = in_array(AutoCrud::class, class_uses_recursive($class));

                $models[] = [
                    'name' => $reflection->getShortName(),
                    'class' => $class,
                    'table' => $instance->getTable(),
                    'autoCrud' => $usesAutoCrud,
                    'fillable' => $instance->getFillable(),
                    'casts' => $instance->getCasts(),
                    'fields' => $usesAutoCrud ? $this->getAutoCrudFields($class) : [],
                    'relations' => $usesAutoCrud ? $this->getAutoCrudRelations($class) : $this->getModelRelations($reflection),
                ];
            } catch (Throwable $e) {
                $models[] = [
                    'name' => class_basename($class),
                    'class' => $class,
                    'error' => $e->getMessage(),
                ];
            }
        }

        usort($models, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $models;
    }

    protected function classFromPhpFile($path)
    {
        $contents = File::get($path);
        $namespace = '';

        if (preg_match('/^\s*namespace\s+([^;]+);/m', $contents, $matches)) {
            $namespace = trim($matches[1]);
        }

        if (!preg_match('/^\s*(?:abstract\s+|final\s+)?class\s+(\w+)/m', $contents, $matches)) {
            return null;
        }

        return ltrim($namespace . '\\' . $matches[1], '\\');
    }

    protected function getAutoCrudFields($class)
    {
        try {
            $method = new ReflectionMethod($class, 'getFields');
            $method->setAccessible(true);
            $fields = $method->invoke(null);

            return is_array($fields) ? $fields : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    protected function getAutoCrudRelations($class)
    {
        $relations = [];

        foreach ($this->getAutoCrudFields($class) as $field) {
            if (isset($field['relation']['relation'])) {
                $relations[] = [
                    'name' => $field['relation']['relation'],
                    'type' => isset($field['relation']['polymorphic']) && $field['relation']['polymorphic'] ? 'morphTo' : 'belongsTo',
                    'model' => isset($field['relation']['model']) ? $field['relation']['model'] : null,
                    'field' => isset($field['field']) ? $field['field'] : null,
                ];
            }
        }

        try {
            if (property_exists($class, 'externalRelations')) {
                $property = (new ReflectionClass($class))->getProperty('externalRelations');
                $property->setAccessible(true);

                foreach ((array) $property->getValue() as $relation) {
                    $relations[] = [
                        'name' => isset($relation['relation']) ? $relation['relation'] : null,
                        'type' => isset($relation['type']) ? $relation['type'] : 'belongsToMany',
                        'model' => isset($relation['model']) ? $relation['model'] : null,
                        'field' => isset($relation['foreignKey']) ? $relation['foreignKey'] : null,
                    ];
                }
            }
        } catch (Throwable $e) {
            // La propiedad puede no existir o no ser accesible en modelos antiguos.
        }

        return array_values(array_filter($relations, function ($relation) {
            return !empty($relation['name']);
        }));
    }

    protected function getModelRelations(ReflectionClass $reflection)
    {
        $relations = [];
        $relationMethods = 'hasOne|hasMany|belongsTo|belongsToMany|morphTo|morphOne|morphMany|morphToMany|morphedByMany';

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getNumberOfParameters() > 0 || $method->class !== $reflection->getName()) {
                continue;
            }

            $body = $this->methodBody($method);

            if (!preg_match('/return\s+\$this->(' . $relationMethods . ')\s*\((.*?)\)/s', $body, $matches)) {
                continue;
            }

            $relations[] = [
                'name' => $method->getName(),
                'type' => $matches[1],
                'model' => $this->extractRelationModel($matches[2]),
                'field' => $this->extractRelationForeignKey($matches[2]),
            ];
        }

        return $relations;
    }

    protected function methodBody(ReflectionMethod $method)
    {
        $file = $method->getFileName();

        if (!$file || !File::exists($file)) {
            return '';
        }

        $lines = file($file);

        return implode('', array_slice($lines, max(0, $method->getStartLine() - 1), max(0, $method->getEndLine() - $method->getStartLine() + 1)));
    }

    protected function extractRelationModel($arguments)
    {
        if (preg_match('/([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)::class/', $arguments, $matches)) {
            return $matches[1] . '::class';
        }

        if (preg_match('/[\'\"]([^\'\"]+)[\'\"]/', $arguments, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function extractRelationForeignKey($arguments)
    {
        if (!preg_match_all('/[\'\"]([^\'\"]+)[\'\"]/', $arguments, $matches) || count($matches[1]) < 2) {
            return null;
        }

        return $matches[1][1];
    }

    protected function inspectDatabase()
    {
        try {
            $tables = $this->getTables();
            $database = [];

            foreach ($tables as $table) {
                $database[] = [
                    'name' => $table,
                    'columns' => $this->getColumns($table),
                    'foreignKeys' => $this->getForeignKeys($table),
                ];
            }

            return $database;
        } catch (Throwable $e) {
            return [[
                'name' => 'Error leyendo base de datos',
                'error' => $e->getMessage(),
                'columns' => [],
                'foreignKeys' => [],
            ]];
        }
    }

    protected function getTables()
    {
        $schema = Schema::getFacadeRoot();

        if ($schema && method_exists($schema, 'getTables')) {
            return array_map(function ($table) {
                return is_array($table) ? ($table['name'] ?? $table['table'] ?? reset($table)) : (string) $table;
            }, $schema->getTables());
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return array_map(function ($row) {
                return $row->name;
            }, DB::select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name"));
        }

        if ($driver === 'pgsql') {
            return array_map(function ($row) {
                return $row->table_name;
            }, DB::select("select table_name from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE' order by table_name"));
        }

        return array_map(function ($row) {
            $values = array_values((array) $row);
            return $values[0];
        }, DB::select('SHOW TABLES'));
    }

    protected function getColumns($table)
    {
        $schema = Schema::getFacadeRoot();

        if ($schema && method_exists($schema, 'getColumns')) {
            return array_map(function ($column) {
                return [
                    'name' => $column['name'] ?? $column['column_name'] ?? null,
                    'type' => $column['type_name'] ?? $column['type'] ?? $column['data_type'] ?? null,
                    'nullable' => $column['nullable'] ?? null,
                ];
            }, $schema->getColumns($table));
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return array_map(function ($row) {
                return ['name' => $row->name, 'type' => $row->type, 'nullable' => !$row->notnull];
            }, DB::select('PRAGMA table_info(' . $this->quoteIdentifier($table) . ')'));
        }

        if ($driver === 'pgsql') {
            return array_map(function ($row) {
                return ['name' => $row->column_name, 'type' => $row->data_type, 'nullable' => $row->is_nullable === 'YES'];
            }, DB::select('select column_name, data_type, is_nullable from information_schema.columns where table_schema = ? and table_name = ? order by ordinal_position', ['public', $table]));
        }

        return array_map(function ($row) {
            return ['name' => $row->Field, 'type' => $row->Type, 'nullable' => $row->Null === 'YES'];
        }, DB::select('SHOW COLUMNS FROM ' . $this->quoteIdentifier($table)));
    }

    protected function getForeignKeys($table)
    {
        $schema = Schema::getFacadeRoot();

        if ($schema && method_exists($schema, 'getForeignKeys')) {
            return array_map(function ($fk) {
                return [
                    'columns' => $fk['columns'] ?? [$fk['column'] ?? null],
                    'foreignTable' => $fk['foreign_table'] ?? $fk['foreignTable'] ?? null,
                    'foreignColumns' => $fk['foreign_columns'] ?? $fk['foreignColumns'] ?? [$fk['foreign_column'] ?? null],
                ];
            }, $schema->getForeignKeys($table));
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return array_map(function ($row) {
                return ['columns' => [$row->from], 'foreignTable' => $row->table, 'foreignColumns' => [$row->to]];
            }, DB::select('PRAGMA foreign_key_list(' . $this->quoteIdentifier($table) . ')'));
        }

        if ($driver === 'pgsql') {
            return array_map(function ($row) {
                return ['columns' => [$row->column_name], 'foreignTable' => $row->foreign_table_name, 'foreignColumns' => [$row->foreign_column_name]];
            }, DB::select("select kcu.column_name, ccu.table_name as foreign_table_name, ccu.column_name as foreign_column_name from information_schema.table_constraints tc join information_schema.key_column_usage kcu on tc.constraint_name = kcu.constraint_name and tc.table_schema = kcu.table_schema join information_schema.constraint_column_usage ccu on ccu.constraint_name = tc.constraint_name and ccu.table_schema = tc.table_schema where tc.constraint_type = 'FOREIGN KEY' and tc.table_schema = ? and tc.table_name = ?", ['public', $table]));
        }

        return array_map(function ($row) {
            return ['columns' => [$row->COLUMN_NAME], 'foreignTable' => $row->REFERENCED_TABLE_NAME, 'foreignColumns' => [$row->REFERENCED_COLUMN_NAME]];
        }, DB::select('select COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME from information_schema.KEY_COLUMN_USAGE where TABLE_SCHEMA = ? and TABLE_NAME = ? and REFERENCED_TABLE_NAME is not null', [DB::connection()->getDatabaseName(), $table]));
    }

    protected function quoteIdentifier($identifier)
    {
        $driver = DB::connection()->getDriverName();
        $quote = $driver === 'mysql' ? '`' : '"';

        return $quote . str_replace($quote, $quote . $quote, $identifier) . $quote;
    }

    protected function discoverInertiaPages()
    {
        $pages = [];

        foreach (Route::getRoutes() as $route) {
            $render = null;
            $controller = null;
            $action = $route->getAction();

            if (isset($action['controller'])) {
                $controller = $action['controller'];
                $render = $this->inertiaRenderFromControllerAction($controller);
            } elseif (isset($action['uses']) && $action['uses'] instanceof \Closure) {
                $render = $this->inertiaRenderFromClosure($action['uses']);
            }

            if ($render) {
                $pages[] = [
                    'component' => $render['component'],
                    'props' => $render['props'],
                    'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
                    'uri' => '/' . ltrim($route->uri(), '/'),
                    'controller' => $controller,
                ];
            }
        }

        return $pages;
    }

    protected function inertiaRenderFromControllerAction($controllerAction)
    {
        if (strpos($controllerAction, '@') === false) {
            return null;
        }

        list($class, $method) = explode('@', $controllerAction, 2);

        try {
            $reflection = new ReflectionMethod($class, $method);
            return $this->inertiaRenderFromFileLines($reflection->getFileName(), $reflection->getStartLine(), $reflection->getEndLine());
        } catch (Throwable $e) {
            return null;
        }
    }

    protected function inertiaRenderFromClosure(\Closure $closure)
    {
        try {
            $reflection = new \ReflectionFunction($closure);
            return $this->inertiaRenderFromFileLines($reflection->getFileName(), $reflection->getStartLine(), $reflection->getEndLine());
        } catch (Throwable $e) {
            return null;
        }
    }

    protected function inertiaRenderFromFileLines($file, $start, $end)
    {
        if (!$file || !File::exists($file)) {
            return null;
        }

        $lines = file($file);
        $chunk = implode('', array_slice($lines, max(0, $start - 1), max(0, $end - $start + 1)));

        if (preg_match('/Inertia::render\s*\(\s*[\'\"]([^\'\"]+)[\'\"]\s*(?:,\s*\[(.*?)\])?/s', $chunk, $matches)) {
            return [
                'component' => $matches[1],
                'props' => $this->extractArrayKeys(isset($matches[2]) ? $matches[2] : ''),
            ];
        }

        if (preg_match('/inertia\s*\(\s*[\'\"]([^\'\"]+)[\'\"]\s*(?:,\s*\[(.*?)\])?/s', $chunk, $matches)) {
            return [
                'component' => $matches[1],
                'props' => $this->extractArrayKeys(isset($matches[2]) ? $matches[2] : ''),
            ];
        }

        return null;
    }

    protected function extractArrayKeys($contents)
    {
        if (!$contents || !preg_match_all('/[\'\"]([^\'\"]+)[\'\"]\s*=>/', $contents, $matches)) {
            return [];
        }

        return array_values(array_unique($matches[1]));
    }

    protected function discoverVueFiles()
    {
        $roots = [resource_path('js/Pages'), resource_path('js/Components')];
        $files = [];

        foreach ($roots as $root) {
            if (!File::isDirectory($root)) {
                continue;
            }

            foreach (File::allFiles($root) as $file) {
                if ($file->getExtension() !== 'vue') {
                    continue;
                }

                $path = $file->getPathname();
                $relative = ltrim(str_replace(resource_path('js'), '', $path), DIRECTORY_SEPARATOR);
                $contents = File::get($path);

                $files[] = [
                    'path' => str_replace(DIRECTORY_SEPARATOR, '/', $relative),
                    'props' => $this->extractDefineList($contents, 'defineProps'),
                    'events' => $this->extractDefineList($contents, 'defineEmits'),
                    'uses' => $this->extractVueUses($contents),
                ];
            }
        }

        usort($files, function ($a, $b) {
            return strcmp($a['path'], $b['path']);
        });

        return $files;
    }

    protected function extractDefineList($contents, $function)
    {
        $items = [];

        if (preg_match('/' . preg_quote($function, '/') . '\s*(?:<[^>]+>)?\s*\((.*?)\)/s', $contents, $matches)) {
            $body = $matches[1];

            if (preg_match_all('/[\'\"]([^\'\"]+)[\'\"]/', $body, $stringMatches)) {
                $items = array_merge($items, $stringMatches[1]);
            }

            if (preg_match_all('/([A-Za-z_$][\w$-]*)\s*:/', $body, $keyMatches)) {
                $items = array_merge($items, $keyMatches[1]);
            }
        }

        return array_values(array_unique($items));
    }

    protected function extractVueUses($contents)
    {
        $uses = [];

        if (preg_match_all('/import\s+([A-Z][A-Za-z0-9_]*)\s+from\s+[\'\"][^\'\"]+[\'\"]/', $contents, $matches)) {
            $uses = array_merge($uses, $matches[1]);
        }

        if (preg_match('/<template[^>]*>(.*?)<\/template>/s', $contents, $template) && preg_match_all('/<([A-Z][A-Za-z0-9_.-]*)\b/', $template[1], $matches)) {
            $uses = array_merge($uses, $matches[1]);
        }

        return array_values(array_unique($uses));
    }

    protected function renderModels($models)
    {
        $lines = ['## Modelos', ''];

        if (empty($models)) {
            return array_merge($lines, ['No se encontraron modelos en `app/Models`.', '']);
        }

        $autoCrud = array_values(array_filter($models, function ($model) {
            return !empty($model['autoCrud']);
        }));
        $normal = array_values(array_filter($models, function ($model) {
            return empty($model['autoCrud']);
        }));

        $lines = array_merge($lines, $this->renderModelGroup('### Modelos AutoCrud', $autoCrud));
        $lines = array_merge($lines, $this->renderModelGroup('### Modelos normales', $normal));

        return $lines;
    }

    protected function renderModelGroup($title, $models)
    {
        $lines = [$title, ''];

        if (empty($models)) {
            return array_merge($lines, ['No hay modelos en este grupo.', '']);
        }

        foreach ($models as $model) {
            $lines[] = '#### ' . $model['name'];
            $lines[] = '';
            $lines[] = 'Clase: `' . $model['class'] . '`';

            if (isset($model['error'])) {
                $lines[] = 'Error: `' . $model['error'] . '`';
                $lines[] = '';
                continue;
            }

            $lines[] = 'Tabla: `' . $model['table'] . '`';
            $lines[] = '';
            $lines[] = 'Fillable:';
            $lines = array_merge($lines, $this->renderBullets($model['fillable']));
            $lines[] = 'Casts:';
            $lines = array_merge($lines, $this->renderKeyValues($model['casts']));

            if (!empty($model['fields'])) {
                $lines[] = 'Campos AutoCrud:';
                foreach ($model['fields'] as $field) {
                    $lines[] = '- ' . ($field['field'] ?? 'sin_campo') . ' (' . ($field['type'] ?? 'sin_tipo') . ')' . (!empty($field['name']) ? ': ' . $field['name'] : '');
                }
            }

            $lines[] = 'Relaciones:';
            if (empty($model['relations'])) {
                $lines[] = '- Ninguna detectada';
            } else {
                foreach ($model['relations'] as $relation) {
                    $line = '- ' . $relation['name'] . ' (' . $relation['type'] . ')';
                    $line .= !empty($relation['model']) ? ' -> `' . $relation['model'] . '`' : '';
                    $line .= !empty($relation['field']) ? ' via `' . $relation['field'] . '`' : '';
                    $lines[] = $line;
                }
            }

            $lines[] = '';
        }

        return $lines;
    }

    protected function renderDatabase($tables)
    {
        $lines = ['## Base de datos', ''];

        foreach ($tables as $table) {
            $lines[] = '### ' . $table['name'];
            $lines[] = '';

            if (isset($table['error'])) {
                $lines[] = 'Error: `' . $table['error'] . '`';
                $lines[] = '';
                continue;
            }

            $lines[] = '| Campo | Tipo | Nullable |';
            $lines[] = '| --- | --- | --- |';

            foreach ($table['columns'] as $column) {
                $lines[] = '| ' . ($column['name'] ?: '') . ' | ' . ($column['type'] ?: '') . ' | ' . ($column['nullable'] === null ? '' : ($column['nullable'] ? 'si' : 'no')) . ' |';
            }

            $lines[] = '';
            $lines[] = 'FK:';

            if (empty($table['foreignKeys'])) {
                $lines[] = '- Ninguna';
            } else {
                foreach ($table['foreignKeys'] as $fk) {
                    $lines[] = '- ' . implode(', ', array_filter($fk['columns'])) . ' -> ' . $fk['foreignTable'] . '.' . implode(', ', array_filter($fk['foreignColumns']));
                }
            }

            $lines[] = '';
        }

        return $lines;
    }

    protected function renderInertiaPages($pages)
    {
        $lines = ['## Páginas Inertia', ''];

        if (empty($pages)) {
            return array_merge($lines, ['No se detectaron rutas que rendericen Inertia.', '']);
        }

        foreach ($pages as $page) {
            $lines[] = '### ' . $page['component'];
            $lines[] = '';
            $lines[] = 'Ruta: `' . implode('|', $page['methods']) . ' ' . $page['uri'] . '`';
            $lines[] = 'Controller: `' . ($page['controller'] ?: 'Closure') . '`';
            $lines[] = 'Props:';
            $lines = array_merge($lines, $this->renderBullets($page['props']));
            $lines[] = '';
        }

        return $lines;
    }

    protected function renderVueComponents($files)
    {
        $pages = array_values(array_filter($files, function ($file) {
            return strpos($file['path'], 'Pages/') === 0;
        }));
        $components = array_values(array_filter($files, function ($file) {
            return strpos($file['path'], 'Components/') === 0;
        }));

        return array_merge(
            $this->renderVueGroup('## Páginas Vue', $pages),
            $this->renderVueGroup('## Componentes Vue', $components)
        );
    }

    protected function renderVueGroup($title, $files)
    {
        $lines = [$title, ''];

        if (empty($files)) {
            return array_merge($lines, ['No se encontraron archivos Vue en este grupo.', '']);
        }

        foreach ($files as $file) {
            $lines[] = '### ' . $file['path'];
            $lines[] = '';
            $lines[] = 'Props:';
            $lines = array_merge($lines, $this->renderBullets($file['props']));
            $lines[] = 'Events:';
            $lines = array_merge($lines, $this->renderBullets($file['events']));
            $lines[] = 'Usa:';
            $lines = array_merge($lines, $this->renderBullets($file['uses']));
            $lines[] = '';
        }

        return $lines;
    }

    protected function renderBullets($items)
    {
        if (empty($items)) {
            return ['- Ninguno'];
        }

        return array_map(function ($item) {
            return '- ' . $item;
        }, $items);
    }

    protected function renderKeyValues($items)
    {
        if (empty($items)) {
            return ['- Ninguno'];
        }

        $lines = [];

        foreach ($items as $key => $value) {
            $lines[] = '- ' . $key . ': ' . (is_string($value) ? $value : json_encode($value));
        }

        return $lines;
    }
}
