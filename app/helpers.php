<?php

use App\Prompts\Answered;
use App\Prompts\DataList;
use App\Prompts\DynamicSpinner;
use App\Prompts\SlideIn;
use Laravel\Prompts\Note;

if (! function_exists('answered')) {
    function answered(string $label, string $answer): void
    {
        (new Answered(label: $label, answer: $answer))->display();
    }
}

if (! function_exists('success')) {
    function success(string $message): void
    {
        (new Note(message: $message, type: 'success'))->display();
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
