<?php

namespace App\Prompts;

use App\Dto\DeploymentLogPhase;
use App\Dto\DeploymentLogStep;
use App\Enums\TimelineSymbol;
use Laravel\Prompts\Themes\Default\Concerns\InteractsWithStrings;

class DeploymentLogsPromptRenderer extends Renderer
{
    use InteractsWithStrings;

    public function __invoke(DeploymentLogsPrompt $prompt): string
    {
        $this->writePhase('Build', $prompt->log->build);
        $this->lineWithBorder('');
        $this->writePhase('Deploy', $prompt->log->deploy);

        return $this;
    }

    protected function writePhase(string $name, DeploymentLogPhase $phase): void
    {
        $this->bullet($this->bold($name), TimelineSymbol::CIRCLE);

        if (! $phase->available || $phase->steps === []) {
            $this->lineWithBorder($this->dim('No '.strtolower($name).' log available.'));

            return;
        }

        foreach ($phase->steps as $step) {
            $this->lineWithBorder('');
            $this->writeStep($step);
        }
    }

    protected function writeStep(DeploymentLogStep $step): void
    {
        $symbol = $this->symbolFor($step->status);
        $details = array_filter([
            $step->status,
            $step->durationMs !== null ? $this->formatDuration($step->durationMs) : null,
        ]);

        $this->bullet($step->description.'  '.$this->dim(implode(' · ', $details)), $symbol);

        $output = rtrim(str_replace("\r\n", "\n", $step->output ?? ''));

        if ($output === '') {
            return;
        }

        $width = max(20, $this->prompt->terminal()->cols() - 6);

        foreach (explode("\n", $output) as $line) {
            // Progress output redraws a line with carriage returns; only the final redraw is meaningful.
            $line = rtrim($line, "\r");
            $line = rtrim(str_contains($line, "\r") ? substr($line, strrpos($line, "\r") + 1) : $line);

            foreach (explode("\n", $this->mbWordwrap($line, $width, "\n", true)) as $wrapped) {
                $this->lineWithBorder($step->failed() ? $wrapped : $this->dim($wrapped));
            }
        }
    }

    protected function symbolFor(string $status): TimelineSymbol
    {
        return match ($status) {
            'finished' => TimelineSymbol::SUCCESS,
            'failed' => TimelineSymbol::FAILURE,
            'running' => TimelineSymbol::PENDING,
            'cancelled', 'skipped' => TimelineSymbol::WARNING,
            default => TimelineSymbol::DOT,
        };
    }

    protected function formatDuration(int $milliseconds): string
    {
        if ($milliseconds < 1000) {
            return $milliseconds.'ms';
        }

        $seconds = $milliseconds / 1000;

        if ($seconds < 60) {
            return number_format($seconds, 1).'s';
        }

        return sprintf('%dm %02ds', intdiv((int) $seconds, 60), (int) $seconds % 60);
    }
}
