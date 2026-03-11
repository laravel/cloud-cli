<?php

namespace App\Prompts;

use App\Concerns\DrawsThemeBoxes;
use App\Enums\TimelineSymbol;
use Laravel\Prompts\SelectPrompt;
use Laravel\Prompts\Themes\Contracts\Scrolling;
use Laravel\Prompts\Themes\Default\Concerns\DrawsScrollbars;

class SelectPromptRenderer extends Renderer implements Scrolling
{
    use DrawsScrollbars;
    use DrawsThemeBoxes;

    /**
     * Render the select prompt.
     */
    public function __invoke(SelectPrompt $prompt): string
    {
        $maxWidth = $prompt->terminal()->cols() - 6;

        return match ($prompt->state) {
            'submit' => $this
                ->box(
                    $this->dim($this->truncate($prompt->label, $prompt->terminal()->cols() - 6)),
                    $this->truncate($prompt->label(), $maxWidth),
                    symbol: TimelineSymbol::SUCCESS,
                ),

            'cancel' => $this
                ->box(
                    $this->truncate($prompt->label, $prompt->terminal()->cols() - 6),
                    $this->renderOptions($prompt),
                    color: 'red',
                    symbol: TimelineSymbol::FAILURE,
                )
                ->error($prompt->cancelMessage),

            'error' => $this
                ->box(
                    $this->truncate($prompt->label, $prompt->terminal()->cols() - 6),
                    $this->renderOptions($prompt),
                    color: 'yellow',
                    symbol: TimelineSymbol::WARNING,
                )
                ->warning($this->truncate($prompt->error, $prompt->terminal()->cols() - 5)),

            default => $this
                ->box(
                    $this->cyan($this->truncate($prompt->label, $prompt->terminal()->cols() - 6)),
                    $this->renderOptions($prompt),
                    symbol: TimelineSymbol::PENDING,
                )
                ->when(
                    $prompt->hint,
                    fn () => $this->hint($prompt->hint),
                    fn () => $this->newLine(), // Space for errors
                ),
        };
    }

    /**
     * Render the options.
     */
    protected function renderOptions(SelectPrompt $prompt): string
    {
        return implode(PHP_EOL, $this->scrollbar(
            array_map(function ($label, $key) use ($prompt) {
                $label = $this->truncate($label, $prompt->terminal()->cols() - 12);

                $index = array_search($key, array_keys($prompt->options));

                if ($prompt->state === 'cancel') {
                    return $this->dim(
                        $prompt->highlighted === $index
                            ? "› ● {$this->strikethrough($label)}  "
                            : "  ○ {$this->strikethrough($label)}  ",
                    );
                }

                return $prompt->highlighted === $index
                    ? "{$this->cyan('›')} {$this->cyan('●')} {$label}  "
                    : "  {$this->dim('○')} {$this->dim($label)}  ";
            }, $visible = $prompt->visible(), array_keys($visible)),
            $prompt->firstVisible,
            $prompt->scroll,
            count($prompt->options),
            min($this->longest($prompt->options, padding: 6), $prompt->terminal()->cols() - 6),
            $prompt->state === 'cancel' ? 'dim' : 'cyan',
        ));
    }

    /**
     * The number of lines to reserve outside of the scrollable area.
     */
    public function reservedLines(): int
    {
        return 5;
    }
}
