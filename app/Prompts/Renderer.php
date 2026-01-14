<?php

namespace App\Prompts;

abstract class Renderer extends \Laravel\Prompts\Themes\Default\Renderer
{
    /**
     * Render the output with a blank line above and below.
     */
    public function __toString()
    {
        return str_repeat('│'.PHP_EOL, max(2 - $this->prompt->newLinesWritten(), 0))
            .$this->output
            .(in_array($this->prompt->state, ['submit', 'cancel']) ? '│'.PHP_EOL : '');
    }
}
