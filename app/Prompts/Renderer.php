<?php

namespace App\Prompts;

use App\Enums\TimelineSymbol;
use Laravel\Prompts\Themes\Default\Renderer as BaseRenderer;

abstract class Renderer extends BaseRenderer
{
    public static bool $suppressOutput = false;

    protected $skipTopBorder = false;

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

        $toWrite = $this->skipTopBorder ? '' : TimelineSymbol::LINE->value;

        return str_repeat($toWrite.PHP_EOL, max(2 - $this->prompt->newLinesWritten(), 0))
            .rtrim($this->output, PHP_EOL)
            .PHP_EOL
            .(
                in_array($this->prompt->state, ['submit'])
                ? TimelineSymbol::LINE->value
                : $this->dim('╰')
            )
            .PHP_EOL;
    }
}
