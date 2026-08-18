<?php

namespace App\Support;

class Stdin
{
    /**
     * Read piped input, or null when STDIN is a terminal or carries nothing.
     */
    public function read(): ?string
    {
        if (stream_isatty(STDIN)) {
            return null;
        }

        $contents = stream_get_contents(STDIN);

        if ($contents === false || trim($contents) === '') {
            return null;
        }

        return trim($contents);
    }
}
