<?php

namespace App\Prompts;

use App\Enums\TimelineSymbol;
use Laravel\Prompts\Themes\Default\Renderer as BaseRenderer;

abstract class Renderer extends BaseRenderer
{
    protected function lineWithBorder(string $message): self
    {
        return $this->line(TimelineSymbol::LINE->value.'  '.$message);
    }

    /**
     * Render a warning message.
     */
    protected function bullet(string $message): self
    {
        return $this->line($this->green(TimelineSymbol::DOT->value)."  {$message}");
    }

    /**
     * Render a warning message.
     */
    protected function warning(string $message): self
    {
        return $this->line(TimelineSymbol::LINE->value.$this->yellow("   {$message}"));
    }

    /**
     * Render an error message.
     */
    protected function error(string $message): self
    {
        return $this->line(TimelineSymbol::LINE->value.$this->red("   {$message}"));
    }

    /**
     * Render an hint message.
     */
    protected function hint(string $message): self
    {
        if ($message === '') {
            return $this;
        }

        $message = $this->truncate($message, $this->prompt->terminal()->cols() - 6);

        return $this->line(TimelineSymbol::LINE->value.$this->gray("   {$message}"));
    }

    /**
     * Render the output with a blank line above and below.
     */
    public function __toString()
    {
        return str_repeat(TimelineSymbol::LINE->value.PHP_EOL, max(2 - $this->prompt->newLinesWritten(), 0))
            .$this->output
            .(in_array($this->prompt->state, ['submit', 'cancel']) ? TimelineSymbol::LINE->value.PHP_EOL : '');
    }
}
