<?php

use App\Prompts\Renderer;
use App\Prompts\SuffixedTextPrompt;
use App\Prompts\TextPromptRenderer;

// Commands run earlier in the suite leave output suppressed, which blanks every frame.
beforeEach(fn () => Renderer::$suppressOutput = false);

function renderSuffixedPrompt(string $state): string
{
    $prompt = new SuffixedTextPrompt(label: 'Sender address', suffix: '@example.com', default: 'team');
    $prompt->state = $state;

    return (new TextPromptRenderer($prompt))($prompt);
}

it('dims the suffix while typing', function () {
    expect(renderSuffixedPrompt('active'))->toContain("\e[2m@example.com\e[22m");
});

it('shows the suffix as part of the submitted answer', function () {
    $frame = renderSuffixedPrompt('submit');

    expect($frame)->toContain('team@example.com')
        ->and($frame)->not->toContain("\e[2m@example.com");
});
