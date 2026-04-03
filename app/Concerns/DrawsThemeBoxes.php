<?php

namespace App\Concerns;

use App\Enums\TimelineSymbol;
use Laravel\Prompts\Themes\Default\Concerns\DrawsBoxes;

trait DrawsThemeBoxes
{
    use DrawsBoxes {
        box as parentBox;
    }

    public function box(string $title, string $body, string $footer = '', string $color = 'gray', string $info = '', ?TimelineSymbol $symbol = TimelineSymbol::PENDING): self
    {
        $originalOutput = $this->output;
        $this->output = '';

        $this->parentBox($title, $body, $footer, $color, $info);

        $replace = [
            '┌' => '╭',
            '└' => '╰',
            '┐' => '╮',
            '┘' => '╯',
        ];

        $this->output = str_replace(array_keys($replace), array_values($replace), $this->output);

        $newOutput = collect(explode(PHP_EOL, $this->output))
            ->map(function ($line, $index) use ($symbol) {
                if (! strlen($line)) {
                    return $line;
                }

                if ($symbol === null) {
                    return TimelineSymbol::LINE->value.' '.$line;
                }

                $color = $symbol->color();

                return ($index === 0 ? $this->{$color}($symbol->value) : TimelineSymbol::LINE->value).' '.$line;
            })
            ->implode(PHP_EOL);

        $this->output = $originalOutput.$newOutput;

        return $this;
    }
}
