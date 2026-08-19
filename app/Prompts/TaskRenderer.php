<?php

namespace App\Prompts;

use App\Enums\TimelineSymbol;
use Laravel\Prompts\Concerns\HasSpinner;
use Laravel\Prompts\Task;
use Laravel\Prompts\Themes\Default\Concerns\InteractsWithStrings;

class TaskRenderer extends Renderer
{
    use HasSpinner;
    use InteractsWithStrings;

    /**
     * The trailing ellipsis frames.
     *
     * @var array<string>
     */
    protected array $ellipsisFrames = ['', '.', '..', '...', '...'];

    /**
     * How many spinner frames each ellipsis frame lasts for.
     */
    protected int $framesPerEllipsis = 10;

    /**
     * Render the task.
     */
    public function __invoke(Task $task): string
    {
        if ($task->static) {
            return $this->line("{$this->cyan($this->staticFrame)} {$task->label}");
        }

        $task->interval = $this->interval;

        if ($task->finished) {
            return $this->summary($task);
        }

        $this->line("{$this->cyan($this->spinnerFrame($task->count))}  {$task->label}{$this->ellipsis($task)}");

        $this->subLabel($task);
        $this->messages($task);
        $this->logs($task);

        return $this;
    }

    /**
     * The frame left on screen by a finished task that keeps its summary.
     */
    protected function summary(Task $task): string
    {
        // The timeline carries on below a finished task rather than closing off with a corner.
        $task->state = 'submit';

        $this->bullet($task->label);
        $this->messages($task);

        return $this;
    }

    /**
     * A label that already ends in punctuation reads as finished, so leave it be.
     */
    protected function ellipsis(Task $task): string
    {
        if (str($this->stripEscapeSequences($task->label))->endsWith(['.', '!'])) {
            return '';
        }

        $frame = intdiv($task->count, $this->framesPerEllipsis) % count($this->ellipsisFrames);

        return $this->dim($this->ellipsisFrames[$frame]);
    }

    /**
     * The dim line under the label, for what the task is doing right now.
     */
    protected function subLabel(Task $task): void
    {
        if ($task->subLabel === null || $task->subLabel === '') {
            return;
        }

        $this->lineWithBorder($this->dim($this->truncate($task->subLabel, $this->maxWidth())));
    }

    /**
     * The successes, warnings and errors the task has reported. Task caps how many it
     * keeps, so everything it still holds is meant to be on screen.
     */
    protected function messages(Task $task): void
    {
        if ($task->stableMessages === []) {
            return;
        }

        $this->lineWithBorder('');

        foreach ($task->stableMessages as $message) {
            $symbol = $this->messageSymbol($message['type']);
            $color = $symbol->color();

            $this->lineWithBorder(
                $this->{$color}($symbol->value).'  '.$this->truncate($message['message'], $this->maxWidth() - 3),
            );
        }
    }

    /**
     * The task's window onto its own output. Task wraps and trims these to fit, and the
     * window is padded to its full height so the lines below it hold still.
     */
    protected function logs(Task $task): void
    {
        if ($task->logs === []) {
            return;
        }

        $this->lineWithBorder('');

        foreach ($task->logs as $log) {
            $this->lineWithBorder($this->dim($log));
        }

        for ($padding = $task->limit - count($task->logs); $padding > 0; $padding--) {
            $this->lineWithBorder('');
        }
    }

    protected function messageSymbol(string $type): TimelineSymbol
    {
        return match ($type) {
            'success' => TimelineSymbol::SUCCESS,
            'error' => TimelineSymbol::FAILURE,
            'warning' => TimelineSymbol::WARNING,
            default => TimelineSymbol::DOT,
        };
    }

    protected function maxWidth(): int
    {
        return $this->prompt->terminal()->cols() - 6;
    }
}
