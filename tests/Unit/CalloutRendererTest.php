<?php

use App\Prompts\CalloutRenderer;
use App\Prompts\Renderer;
use Laravel\Prompts\Callout;

// Commands run earlier in the suite leave output suppressed, which blanks every frame.
beforeEach(fn () => Renderer::$suppressOutput = false);

/**
 * The box lines of the rendered callout with colour codes stripped.
 *
 * @return array<int, string>
 */
function calloutBoxLines(string $content): array
{
    $callout = new Callout('Routing traffic', $content, 'success', '2s');

    $frame = preg_replace('/\e\[[0-9;]*m/', '', (new CalloutRenderer($callout))($callout));

    return collect(explode(PHP_EOL, $frame))
        ->filter(fn (string $line) => str_contains($line, '╭') || str_contains($line, '╰─') || mb_substr_count($line, '│') > 1)
        ->values()
        ->all();
}

it('keeps every line of the box the same width when the content has tabs', function () {
    $lines = calloutBoxLines("Routing to 1 domain(s):\n\t- example.laravel.cloud");

    expect($lines)->toHaveCount(4);
    expect(array_unique(array_map('mb_strwidth', $lines)))->toHaveCount(1);
    expect($lines[2])->not->toContain("\t");
});
