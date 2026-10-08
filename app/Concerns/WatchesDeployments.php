<?php

namespace App\Concerns;

use App\Dto\Deployment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Support\Sleep;
use Laravel\Prompts\Support\Logger;

use function Laravel\Prompts\task;

trait WatchesDeployments
{
    /**
     * Follow a deployment until it finishes and return its final state.
     */
    protected function watchDeployment(Deployment $deployment): Deployment
    {
        task(
            label: $this->getDeploymentMessage($deployment),
            callback: fn (Logger $log) => $this->updateDeploymentStatus($deployment, $log),
        );

        return $this->client->deployments()->get($deployment->id);
    }

    protected function updateDeploymentStatus(Deployment $deployment, Logger $log): void
    {
        $checkApi = true;
        $count = 0;
        $checkInterval = 3;
        $updateInterval = 900;
        $lastMessage = '';
        $deploymentStatus = $this->client->deployments()->get($deployment->id);

        do {
            if ($checkApi) {
                $deploymentStatus = $this->client->deployments()->get($deployment->id);
            }

            $newMessage = $this->getDeploymentMessage($deploymentStatus);

            if (! $this->isInteractive() && $lastMessage !== $deploymentStatus->status->monitorLabel()) {
                $this->line(json_encode([
                    'status' => $deploymentStatus->status->value,
                    'message' => $deploymentStatus->status->monitorLabel(),
                    'timestamp' => CarbonImmutable::now()->timestamp,
                ]));
            }

            $log->label($newMessage);

            $lastMessage = $deploymentStatus->status->monitorLabel();

            Sleep::for(CarbonInterval::milliseconds($updateInterval));
            $count++;
            $checkApi = $count % $checkInterval === 0;
        } while ($deploymentStatus->isInProgress());
    }

    protected function getDeploymentMessage(Deployment $deployment): string
    {
        return $this->dim($deployment->timeElapsed()->format('%I:%S')).' '.$deployment->status->monitorLabel();
    }
}
