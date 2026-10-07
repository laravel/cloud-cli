<?php

namespace App\Prompts;

use App\Concerns\DrawsThemeBoxes;
use App\Enums\TimelineSymbol;
use InvalidArgumentException;
use Laravel\Prompts\Callout;

class CalloutRenderer extends Renderer
{
    use DrawsThemeBoxes;

    public function __invoke(Callout $prompt): string
    {
        // Leaves room for the timeline gutter and the box's borders and padding.
        $width = $prompt->terminal()->cols() - 8;

        $content = is_array($prompt->content) ? $prompt->content : [$prompt->content];

        $body = collect($content)
            ->map(function ($part) use ($width) {
                if (! is_string($part)) {
                    throw new InvalidArgumentException('Unsupported callout content part: '.get_debug_type($part));
                }

                // A tab counts as one column when sizing the box but the terminal draws it wider, pushing the right border out.
                $part = str_replace("\t", '    ', rtrim($part));

                return implode(PHP_EOL, $this->ansiWordwrap($part, $width));
            })
            ->implode(PHP_EOL.PHP_EOL);

        $symbol = match ($prompt->type) {
            'error' => TimelineSymbol::FAILURE,
            'warning' => TimelineSymbol::WARNING,
            'success' => TimelineSymbol::SUCCESS,
            default => TimelineSymbol::DOT,
        };

        return $this->box(
            $this->{$symbol->color()}($this->truncate($prompt->label, $width)),
            $body,
            info: $prompt->info,
            symbol: $symbol,
        );
    }
}
