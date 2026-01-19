<?php

namespace App\Prompts;

use App\Concerns\DrawsThemeBoxes;
use App\Enums\TimelineSymbol;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;

class MonitorDeploymentsRenderer extends Renderer
{
    use DrawsThemeBoxes;

    /**
     * The frames of the spinner.
     *
     * @var array<string>
     */
    protected array $frames = ['⠂', '⠒', '⠐', '⠰', '⠠', '⠤', '⠄', '⠆'];

    public array $ellipsisFrames = ['', '.', '..', '...', '...'];

    public function __invoke(MonitorDeployments $monitor): string
    {
        if ($monitor->lastDeployment) {
            $body = 'Deployed "'.$this->cyan($monitor->lastDeployment->commitMessage).'"';

            if ($monitor->lastDeployment->commitAuthor) {
                $body .= ' by '.$this->cyan($monitor->lastDeployment->commitAuthor);
            }

            $body .= ' in <comment>'.$monitor->lastDeployment->totalTime()->format('%I:%S').'</comment>';
            $body .= PHP_EOL;
            $body .= PHP_EOL;
            $body .= $this->dim('Press').' o '.$this->dim('to open in browser');

            $this->box(
                title: $this->dim('Last Deployment'),
                body: $body,
                info: $monitor->lastDeployment->startedAt?->toDateTimeString(),
                symbol: TimelineSymbol::SUCCESS,
            );

            $this->lineWithBorder('');
        }

        if ($monitor->deployment === null) {
            $checkingIn = max(0, $monitor->checkEvery - ($monitor->lastCheck?->diffInSeconds(CarbonImmutable::now()) ?? 0));

            $message = $this->dim(
                'Checking in for new deployment in '.
                    CarbonInterval::seconds($checkingIn)->format('%I:%S')
            );

            $frame = $this->frames[$monitor->count % count($this->frames)];

            if ($checkingIn < 1) {
                $message .= ' '.$this->cyan($frame).' '.($monitor->fade ? $this->dim('Checking...') : 'Checking...');
                $monitor->fade = false;
            } elseif (! $monitor->fade) {
                $message .= ' '.$this->cyan($frame).' '.$this->dim('Checking...');
                $monitor->fade = true;
            }

            $this->lineWithBorder($message);
        } else {
            $message = 'Deploying "'.$this->cyan($monitor->deployment->commitMessage).'"';

            if ($monitor->deployment->commitAuthor) {
                $message .= ' by '.$this->cyan($monitor->deployment->commitAuthor);
            }

            $this->lineWithBorder($message);
            $this->lineWithBorder('');

            $this->lineWithBorder($this->dim($monitor->deployment->timeElapsed()->format('%I:%S')).' '.$monitor->deployment->status->label().$this->ellipsisFrames[$monitor->ellipsisCount % count($this->ellipsisFrames)]);
        }

        if ($monitor->autoExitAt) {
            $this->lineWithBorder('');

            if ($monitor->autoExitAt->isFuture()) {
                $this->lineWithBorder(
                    $this->dim('Auto-exiting in '.$monitor->autoExitAt->diff(CarbonImmutable::now())->format('%I:%S'))
                );
            } else {
                $this->lineWithBorder($this->dim('Auto-exiting after deployment'));
            }
        }

        return $this;
    }
}
