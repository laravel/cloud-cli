<?php

namespace App\Prompts;

use App\Enums\TimelineSymbol;
use Laravel\Prompts\Note;
use Laravel\Prompts\Themes\Default\Concerns\InteractsWithStrings;

class NoteRenderer extends Renderer
{
    use InteractsWithStrings;

    /**
     * Render the note.
     */
    public function __invoke(Note $note): string
    {
        $width = $note::terminal()->cols() - 10;
        $lines = explode(PHP_EOL, $this->mbWordwrap($note->message, width: $width));
        $spacer = str_repeat(' ', 2);

        switch ($note->type) {
            case 'intro':
            case 'outro':
                $lines = array_map(fn ($line) => "{$line} ", $lines);
                $longest = max(array_map(fn ($line) => strlen($line), $lines));

                if ($note->type === 'intro') {
                    $this->skipTopBorder = true;
                    $this->line($this->dim('╭'));
                }

                foreach ($lines as $line) {
                    $line = str_pad($line, $longest, ' ');

                    if (trim($line) === '') {
                        $this->line($this->dim(TimelineSymbol::LINE->value.$spacer.$line));
                    } else {
                        $this->line($this->cyan(TimelineSymbol::DOT->value.$spacer.$line));
                    }
                }

                if ($note->type === 'outro') {
                    $this->prompt->state = 'initial';
                    static::markCommandShowedOutro();
                }

                return $this;

            case 'warning':
                foreach ($lines as $index => $line) {
                    $this->line($this->symbolOrLine(TimelineSymbol::WARNING, 'yellow', $line, $index));
                }

                return $this;

            case 'error':
                foreach ($lines as $index => $line) {
                    $this->line($this->symbolOrLine(TimelineSymbol::FAILURE, 'red', $line, $index));
                }

                return $this;

            case 'alert':
                foreach ($lines as $line) {
                    $this->line($this->bgRed($this->white(TimelineSymbol::FAILURE->value.$spacer.$line)));
                }

                return $this;

            case 'info':
                foreach ($lines as $index => $line) {
                    $this->line($this->symbolOrLine(TimelineSymbol::DOT, 'green', $line, $index));
                }

                return $this;

            case 'success':
                foreach ($lines as $index => $line) {
                    $this->line($this->symbolOrLine(TimelineSymbol::SUCCESS, 'green', $line, $index));
                }

                return $this;

            default:
                foreach ($lines as $line) {
                    $this->line(TimelineSymbol::LINE->value.$spacer.$line);
                }

                return $this;
        }
    }

    public function symbolOrLine(TimelineSymbol $symbol, string $color, string $line, int $index): string
    {
        $content = str_repeat(' ', 2).$this->{$color}($line);

        if ($index === 0) {
            return $this->{$color}($symbol->value).$content;
        }

        return TimelineSymbol::LINE->value.$content;
    }
}
