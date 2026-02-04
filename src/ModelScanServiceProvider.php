<?php

namespace Ismaelcmajada\LaravelAutoCrud;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;

class ModelScanServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('models', function ($app) {
            return $this->getModels();
        });
    }

    private function getModels()
    {
        $modelsPath = app_path('Models');
        $modelFiles = File::allFiles($modelsPath);
        $models = [];

        foreach ($modelFiles as $modelFile) {
            // getRelativePathname() puede devolver paths con / o \ según entorno
            $relative = $modelFile->getRelativePathname();

            // Quitar extensión .php y convertir separadores a namespace
            $relative = str_replace(['/', '\\'], '\\', $relative);
            $relative = preg_replace('/\.php$/', '', $relative);

            $className = '\\App\\Models\\' . $relative;

            if (class_exists($className)) {
                $reflection = new ReflectionClass($className);

                if (
                    $reflection->isSubclassOf('Illuminate\\Database\\Eloquent\\Model') &&
                    !$reflection->isAbstract() &&
                    isset(class_uses_recursive($className)['Ismaelcmajada\\LaravelAutoCrud\\Models\\Traits\\AutoCrud'])
                ) {
                    $modelName = Str::lower(Str::afterLast($className, '\\'));
                    $models[$modelName] = $className::getModel();
                }
            }
        }

        return $models;
    }
}
