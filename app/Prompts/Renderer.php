<?php

namespace App\Prompts;

use App\Enums\TimelineSymbol;
use Laravel\Prompts\Themes\Default\Renderer as BaseRenderer;

abstract class Renderer extends BaseRenderer
{
    public static bool $suppressOutput = false;

    protected function lineWithBorder(string $message): self
    {
        return $this->line(TimelineSymbol::LINE->value.'  '.$message);
    }

    /**
     * Render a warning message.
     */
    protected function bullet(string $message, TimelineSymbol $symbol = TimelineSymbol::DOT): self
    {
        $color = $symbol->color();

        return $this->line($this->{$color}($symbol->value).'  '.$message);
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
        if (self::$suppressOutput) {
            return '';
        }

        return str_repeat(TimelineSymbol::LINE->value.PHP_EOL, max(2 - $this->prompt->newLinesWritten(), 0))
            .$this->output
            .(in_array($this->prompt->state, ['submit', 'cancel']) ? TimelineSymbol::LINE->value.PHP_EOL : '');
    }
}
