<?php

namespace App\Prompts;

use App\Enums\TimelineSymbol;
use Laravel\Prompts\Themes\Default\Renderer as BaseRenderer;

abstract class Renderer extends BaseRenderer
{
    public static bool $suppressOutput = false;

    protected static bool $commandAlreadyShowedOutro = false;

    protected $skipTopBorder = false;

    public static function commandAlreadyShowedOutro(): bool
    {
        return self::$commandAlreadyShowedOutro;
    }

    public static function markCommandShowedOutro(): void
    {
        self::$commandAlreadyShowedOutro = true;
    }

    public static function resetOutroFlag(): void
    {
        self::$commandAlreadyShowedOutro = false;
    }

    protected function lineWithBorder(string $message): static
    {
        $this->line(TimelineSymbol::LINE->value.'  '.$message);

        return $this;
    }

    /**
     * Render a warning message.
     */
    protected function bullet(string $message, TimelineSymbol $symbol = TimelineSymbol::DOT): static
    {
        $color = $symbol->color();

        $this->line($this->{$color}($symbol->value).'  '.$message);

        return $this;
    }

    /**
     * Render a warning message.
     */
    protected function warning(string $message): static
    {
        $this->line(TimelineSymbol::LINE->value.$this->yellow("   {$message}"));

        return $this;
    }

    /**
     * Render an error message.
     */
    protected function error(string $message): static
    {
        $this->line(TimelineSymbol::LINE->value.$this->red("   {$message}"));

        return $this;
    }

    /**
     * Render an hint message.
     */
    protected function hint(string $message): static
    {
        if ($message === '') {
            return $this;
        }

        $message = $this->truncate($message, $this->prompt->terminal()->cols() - 6);

        $this->line(TimelineSymbol::LINE->value.$this->gray("   {$message}"));

        return $this;
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
