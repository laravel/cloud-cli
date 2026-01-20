<?php

namespace App\Prompts;

use App\Enums\TimelineSymbol;
use Laravel\Prompts\Note;

class NoteRenderer extends Renderer
{
    /**
     * Render the note.
     */
    public function __invoke(Note $note): string
    {
        $lines = explode(PHP_EOL, $note->message);

        $spacer = str_repeat(' ', 2);

        switch ($note->type) {
            case 'intro':
            case 'outro':
                $lines = array_map(fn ($line) => "{$line} ", $lines);
                $longest = max(array_map(fn ($line) => strlen($line), $lines));

                foreach ($lines as $line) {
                    $line = str_pad($line, $longest, ' ');
                    $this->line($this->cyan(TimelineSymbol::DOT->value.$spacer.$line));
                }

                return $this;

            case 'warning':
                foreach ($lines as $line) {
                    $this->line($this->yellow(TimelineSymbol::WARNING->value.$spacer.$line));
                }

                return $this;

            case 'error':
                foreach ($lines as $line) {
                    $this->line($this->red(TimelineSymbol::FAILURE->value.$spacer.$line));
                }

                return $this;

            case 'alert':
                foreach ($lines as $line) {
                    $this->line($this->bgRed($this->white(TimelineSymbol::FAILURE->value.$spacer.$line)));
                }

                return $this;

            case 'info':
                foreach ($lines as $line) {
                    $this->line($this->green(TimelineSymbol::DOT->value.$spacer.$line));
                }

                return $this;

            case 'success':
                foreach ($lines as $line) {
                    $this->line($this->green(TimelineSymbol::SUCCESS->value.$spacer.$line));
                }

                return $this;

            default:
                foreach ($lines as $line) {
                    $this->line(TimelineSymbol::LINE->value.$spacer.$line);
                }

                return $this;
        }
    }
}
