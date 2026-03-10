<?php

namespace Ismaelcmajada\LaravelAutoCrud\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AutoCrudActionCompleted
{
    use Dispatchable, SerializesModels;

    public $action;
    public $model;
    public $instance;
    public $data;
    public $extra;

    public function __construct(string $action, string $model, $instance = null, array $data = [], array $extra = [])
    {
        $this->action = $action;
        $this->model = $model;
        $this->instance = $instance;
        $this->data = $data;
        $this->extra = $extra;
    }
}
