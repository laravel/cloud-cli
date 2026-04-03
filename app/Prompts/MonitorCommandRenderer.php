<?php

namespace App\Prompts;

use App\Concerns\DrawsThemeBoxes;
use App\Enums\CommandStatus;
use App\Enums\TimelineSymbol;
use Laravel\Prompts\Themes\Default\Concerns\InteractsWithStrings;

class MonitorCommandRenderer extends Renderer
{
    use DrawsThemeBoxes;
    use InteractsWithStrings;

    /**
     * The frames of the spinner.
     *
     * @var array<string>
     */
    protected array $frames = ['⠂', '⠒', '⠐', '⠰', '⠠', '⠤', '⠄', '⠆'];

    public array $ellipsisFrames = ['', '.', '..', '...', '...'];

    public function __invoke(MonitorCommand $monitor): string
    {
        if ($monitor->lastCommand) {
            $command = $monitor->lastCommand;
            $body = $this->cyan($command->command);
            $body .= PHP_EOL;
            $body .= PHP_EOL;
            $body .= 'Completed in <comment>'.$command->totalTime()->format('%I:%S').'</comment>';

            if ($command->exitCode !== null) {
                $body .= ' with exit code <comment>'.$command->exitCode.'</comment>';
            }

            $symbol = $command->status === CommandStatus::SUCCESS ? TimelineSymbol::SUCCESS : TimelineSymbol::FAILURE;

            if ($command->output) {
                $body .= PHP_EOL;
                $body .= PHP_EOL;
                $body .= $this->mbWordwrap($command->output, $monitor->terminal()->cols() - 15, cut_long_words: true);
            }

            $this->box(
                title: $this->dim('Command Completed'),
                body: $body,
                info: $command->startedAt?->toDateTimeString() ?? '',
                symbol: $symbol,
            );

            $this->lineWithBorder('');
        }

        if ($monitor->command !== null) {
            $message = 'Running '.$this->cyan($monitor->command->command);
            $this->lineWithBorder($message);
            $this->lineWithBorder('');

            $this->lineWithBorder(
                $this->dim($monitor->command->timeElapsed()->format('%I:%S')).' '.
                    $monitor->command->status->label().
                    $this->ellipsisFrames[$monitor->ellipsisCount % count($this->ellipsisFrames)],
            );
        }

        return $this;
    }
}
