<?php

namespace App\Prompts;

use App\Concerns\DrawsThemeBoxes;
use App\Enums\TimelineSymbol;
use Laravel\Prompts\PasswordPrompt;

class PasswordPromptRenderer extends Renderer
{
    use DrawsThemeBoxes;

    /**
     * Render the password prompt.
     */
    public function __invoke(PasswordPrompt $prompt): string
    {
        $maxWidth = $prompt->terminal()->cols() - 6;

        return match ($prompt->state) {
            'submit' => $this
                ->box(
                    $this->dim($prompt->label),
                    $this->truncate($prompt->masked(), $maxWidth),
                    symbol: TimelineSymbol::SUCCESS,
                ),

            'cancel' => $this
                ->box(
                    $this->truncate($prompt->label, $prompt->terminal()->cols() - 6),
                    $this->strikethrough($this->dim($this->truncate($prompt->masked() ?: $prompt->placeholder, $maxWidth))),
                    color: 'red',
                    symbol: TimelineSymbol::FAILURE,
                )
                ->error($prompt->cancelMessage),

            'error' => $this
                ->box(
                    $this->dim($this->truncate($prompt->label, $prompt->terminal()->cols() - 6)),
                    $prompt->maskedWithCursor($maxWidth),
                    color: 'yellow',
                    symbol: TimelineSymbol::WARNING,
                )
                ->warning($this->truncate($prompt->error, $prompt->terminal()->cols() - 5)),

            default => $this
                ->box(
                    $this->cyan($this->truncate($prompt->label, $prompt->terminal()->cols() - 6)),
                    $prompt->maskedWithCursor($maxWidth),
                    symbol: TimelineSymbol::PENDING,
                )
                ->when(
                    $prompt->hint,
                    fn () => $this->hint($prompt->hint),
                    fn () => $this->newLine(), // Space for errors
                ),
        };
    }
}
