<?php

use App\Prompts\Answered;
use App\Prompts\DataList;
use App\Prompts\DynamicSpinner;
use App\Prompts\SelectWithContextPrompt;
use App\Prompts\SlideIn;
use Laravel\Prompts\DataTable;
use Laravel\Prompts\Note;

if (! function_exists('answered')) {
    function answered(string $label, string $answer, ?string $info = null): void
    {
        (new Answered(label: $label, answer: $answer, info: $info))->display();
    }
}

if (! function_exists('success')) {
    function success(string $message): void
    {
        (new Note(message: $message, type: 'success'))->display();
    }
}

if (! function_exists('selectWithContext')) {
    function selectWithContext(string $label, array $options, int|string|null $default = null, int $scroll = 5, mixed $validate = null, string $hint = '', bool|string $required = true, ?Closure $transform = null): string
    {
        return (new SelectWithContextPrompt(label: $label, options: $options, default: $default, scroll: $scroll, validate: $validate, hint: $hint, required: $required, transform: $transform))->prompt();
    }
}

if (! function_exists('slideIn')) {
    function slideIn(string $message): void
    {
        (new SlideIn(message: $message))->animate();
    }
}

if (! function_exists('dynamicSpinner')) {
    function dynamicSpinner(callable $callback, string $message): mixed
    {
        return (new DynamicSpinner(message: $message))->spin($callback);
    }
}

if (! function_exists('dataList')) {
    function dataList(array $data): void
    {
        (new DataList(data: $data))->display();
    }
}

if (! function_exists('dataTable')) {
    function dataTable(array $headers, array $rows, array $actions = []): void
    {
        (new DataTable(headers: $headers, rows: $rows, actions: $actions))->display();
    }
}
