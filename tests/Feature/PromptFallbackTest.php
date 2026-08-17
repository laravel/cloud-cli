<?php

use Illuminate\Support\Facades\Artisan;
use Laravel\Prompts\Prompt;
use Tests\Fixtures\PromptFallbackTestCommand;

/**
 * Stand in for `windows_os()`, which is what puts prompts into fallback mode on a
 * terminal that can't render them.
 */
function forcePromptFallback(bool $condition): void
{
    (new ReflectionProperty(Prompt::class, 'shouldFallback'))->setValue(null, $condition);
}

beforeEach(function () {
    Artisan::registerCommand(new PromptFallbackTestCommand);

    forcePromptFallback(true);
});

afterEach(function () {
    forcePromptFallback(false);
});

it('falls back to console questions for prompts Illuminate does not register', function () {
    $this->artisan('test:prompt-fallback')
        ->expectsChoice('Application', 'app-2', ['app-1' => 'First app', 'app-2' => 'Second app'])
        ->expectsQuestion('Command', 'php artisan migrate')
        ->expectsOutputToContain('selected:app-2')
        ->expectsOutputToContain('typed:php artisan migrate')
        ->assertSuccessful();
});
