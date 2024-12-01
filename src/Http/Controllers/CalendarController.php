<?php

namespace Ismaelcmajada\LaravelAutoCrud\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Request;


class CalendarController extends Controller
{
    private function getModel($model)
    {
        $modelClass = 'App\\Models\\' . ucfirst($model);

        if (class_exists($modelClass)) {
            return new $modelClass;
        } else {
            abort(404, 'Model not found');
        }
    }

    public function loadEvents($model)
    {
        $modelInstance = $this->getModel($model);
        $eventFields = $modelInstance::getCalendarFields();
        $query = $modelInstance::query();

        $query->with($modelInstance::getIncludes());

        $startDate = request('start');
        $endDate = request('end');

        if ($startDate && $endDate) {
            $query->where(function ($query) use ($eventFields, $startDate, $endDate) {
                $query->whereBetween($eventFields['start'], [$startDate, $endDate])
                    ->orWhereBetween($eventFields['end'], [$startDate, $endDate])
                    ->orWhere(function ($query) use ($eventFields, $startDate, $endDate) {
                        $query->where($eventFields['start'], '<=', $startDate)
                            ->where($eventFields['end'], '>=', $endDate);
                    });
            });
        }

        $items = $query->whereNotNull($eventFields['start'])->whereNotNull($eventFields['end'])->get();

        $events = $items->flatMap(function ($item) use ($eventFields) {
            $events = [];

            // Procesar el título reemplazando los placeholders con los valores reales
            $title = $eventFields['title'];

            if (isset($eventFields['separateEvents']) && $eventFields['separateEvents']) {
                // Generar dos eventos separados
                $startEvent = [
                    'start' => $item->{$eventFields['start']},
                    'end' => $item->{$eventFields['start']},
                    'title' => $title,
                    'item' => $item,
                    'class' => $eventFields['startClass'] ?? 'cell',
                    'drag' => true,
                ];
                $endEvent = [
                    'start' => $item->{$eventFields['end']},
                    'end' => $item->{$eventFields['end']},
                    'title' => $title,
                    'item' => $item,
                    'class' => $eventFields['endClass'] ?? 'cell',
                    'drag' => true,
                ];
                $events[] = $startEvent;
                $events[] = $endEvent;
            } else {
                // Generar un evento de rango
                $event = [
                    'start' => $item->{$eventFields['start']},
                    'end' => $item->{$eventFields['end']},
                    'title' => $title,
                    'item' => $item,
                    'class' => 'cell',
                    'drag' => true,
                ];
                $events[] = $event;
            }
            return $events;
        });

        return [
            'eventsData' => [
                'items' => $events,
            ]
        ];
    }
}
