<?php

namespace App\Commands;

use App\Dto\DeploymentLog;
use App\Dto\DeploymentLogPhase;
use App\Dto\DeploymentLogStep;
use Saloon\Exceptions\Request\RequestException;

use function Laravel\Prompts\callout;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class DeploymentLogs extends BaseCommand
{
    protected ?string $jsonDataClass = DeploymentLog::class;

    protected $signature = 'deployment:logs
                            {deployment? : The deployment ID}';

    protected $description = 'View the build and deploy logs for a deployment';

    public function handle()
    {
        $this->ensureClient();

        intro('Deployment Logs');

        $deployment = $this->resolvers()->deployment()->from($this->argument('deployment'));

        try {
            $log = spin(
                fn () => $this->client->deployments()->logs($deployment->id),
                'Fetching deployment logs...',
            );
        } catch (RequestException $e) {
            $message = $e->getResponse()->json('message') ?? $e->getMessage();

            $this->writeJsonIfWanted($message);

            error($message);

            return self::FAILURE;
        }

        $this->outputJsonIfWanted($log);

        $this->displayPhase('Build', $log->build);
        $this->displayPhase('Deploy', $log->deploy);
    }

    protected function displayPhase(string $label, DeploymentLogPhase $phase): void
    {
        if (! $phase->available) {
            warning("{$label} logs are not available.");

            return;
        }

        info($label);

        foreach ($phase->steps as $step) {
            $this->displayStep($step);
        }
    }

    protected function displayStep(DeploymentLogStep $step): void
    {
        // A cancelled, pending or skipped step never ran, so a box for it would only bury the step that failed.
        if (in_array($step->status, ['cancelled', 'pending', 'skipped'], true)) {
            note("{$step->description} ({$step->status})");

            return;
        }

        $output = $step->output !== null ? rtrim($step->output) : '';

        callout(
            label: $step->description,
            content: $output !== '' ? $output : '—',
            type: match ($step->status) {
                'finished' => 'success',
                'failed' => 'error',
                default => 'warning',
            },
            info: collect([$step->status === 'running' ? 'running' : null, $step->formattedDuration()])->filter()->implode(' · '),
        );
    }
}
