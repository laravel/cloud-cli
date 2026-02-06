<?php

namespace App\Concerns;

use App\Client\Requests\UpdateEnvironmentRequestData;
use App\Dto\Environment;

use function Laravel\Prompts\textarea;

trait UpdatesBuildDeployCommands
{
    protected function updateCommands(Environment $environment): void
    {
        $buildCommand = textarea(
            label: 'Build command',
            default: $environment->buildCommand,
            required: true,
        );

        $deployCommand = textarea(
            label: 'Deploy command',
            default: $environment->deployCommand,
            required: true,
        );

        $this->loopUntilValid(
            function () use ($environment, $buildCommand, $deployCommand) {
                return dynamicSpinner(
                    fn () => $this->client->environments()->update(new UpdateEnvironmentRequestData(
                        environmentId: $environment->id,
                        buildCommand: $buildCommand,
                        deployCommand: $deployCommand,
                    )),
                    'Updating commands',
                );
            },
        );
    }
}
