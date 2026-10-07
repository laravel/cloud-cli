<?php

namespace App\Prompts;

use App\Concerns\DrawsThemeBoxes;
use App\Enums\TimelineSymbol;
use Laravel\Prompts\TextPrompt;

class TextPromptRenderer extends Renderer
{
    use DrawsThemeBoxes;

    /**
     * Render the text prompt.
     */
    public function __invoke(TextPrompt|Answered $prompt): string
    {
        $suffix = $prompt instanceof SuffixedTextPrompt ? $prompt->suffix : '';
        $maxWidth = $prompt->terminal()->cols() - 6 - mb_strwidth($suffix);

        return match ($prompt->state) {
            'submit' => $this
                ->box(
                    $this->dim($this->truncate($prompt->label, $prompt->terminal()->cols() - 6)),
                    $this->truncate($prompt->value(), $maxWidth).$suffix,
                    symbol: TimelineSymbol::SUCCESS,
                    info: $prompt instanceof Answered && $prompt->info ? $prompt->info : '',
                ),

            'cancel' => $this
                ->box(
                    $this->truncate($prompt->label, $prompt->terminal()->cols() - 6),
                    $this->strikethrough($this->dim($this->truncate($prompt->value() ?: $prompt->placeholder, $maxWidth).$suffix)),
                    color: 'red',
                    symbol: TimelineSymbol::FAILURE,
                )
                ->error($prompt->cancelMessage),

            'error' => $this
                ->box(
                    $this->truncate($prompt->label, $prompt->terminal()->cols() - 6),
                    $prompt->valueWithCursor($maxWidth).$this->dim($suffix),
                    color: 'yellow',
                    symbol: TimelineSymbol::WARNING,
                )
                ->warning($this->truncate($prompt->error, $prompt->terminal()->cols() - 5)),

            default => $this
                ->box(
                    $this->cyan($this->truncate($prompt->label, $prompt->terminal()->cols() - 6)),
                    $prompt->valueWithCursor($maxWidth).$this->dim($suffix),
                )
                ->when(
                    $prompt->hint,
                    fn () => $this->hint($prompt->hint),
                    fn () => $this->newLine(), // Space for errors
                ),
        };
    }
}
