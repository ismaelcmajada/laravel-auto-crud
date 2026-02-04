<?php

namespace Ismaelcmajada\LaravelAutoCrud\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class DateWithUserTimezone implements CastsAttributes
{

    protected $format;

    public function __construct($format = 'Y-m-d')
    {
        $this->format = $format;
    }


    /**
     * Cast the given value.
     *
     * @param  Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return mixed
     */
    public function get($model, $key, $value, $attributes)
    {
        if (is_null($value)) {
            return $value;
        }

        // Obtener la zona horaria del usuario logueado
        $timezone = auth()->user()->timezone ?? config('laravel-auto-crud.timezone');

        // Convertir la fecha a la zona horaria del usuario
        return Carbon::parse($value)->setTimezone($timezone)->format($this->format);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return mixed
     */
    public function set($model, $key, $value, $attributes)
    {
        if (is_null($value)) {
            return $value;
        }

        // Convertir la fecha a UTC antes de almacenarla
        return Carbon::parse($value)->setTimezone(config('app.timezone'))->toDateTimeString();
    }
}
