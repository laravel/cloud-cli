<?php

use App\Prompts\Answered;
use App\Prompts\CodeBlock;
use App\Prompts\DataList;
use App\Prompts\DataTable;
use App\Prompts\DynamicSpinner;
use App\Prompts\SelectWithContextPrompt;
use App\Prompts\SlideIn;
use Illuminate\Support\Facades\Process;
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

if (! function_exists('openUrl')) {
    /**
     * Open a URL or file in the user's default browser or file manager.
     */
    function openUrl(string $url): void
    {
        $command = match (PHP_OS_FAMILY) {
            'Darwin' => ['open', $url],
            'Windows' => ['rundll32', 'url.dll,FileProtocolHandler', $url],
            'Linux' => ['xdg-open', $url],
            default => ['open', $url],
        };

        Process::run($command);
    }
}

if (! function_exists('codeBlock')) {
    function codeBlock(string $code, string $language = 'php'): void
    {
        (new CodeBlock(code: $code, language: $language))->display();
    }
}

if (! function_exists('revealFile')) {
    /**
     * Reveal a file in the system file manager (Finder, Explorer, etc.).
     */
    function revealFile(string $path): void
    {
        $command = match (PHP_OS_FAMILY) {
            'Darwin' => ['open', $path, '-R'],
            'Windows' => ['explorer', '/select,'.$path],
            'Linux' => ['xdg-open', dirname($path)],
            default => ['open', $path, '-R'],
        };

        Process::run($command);
    }
}
