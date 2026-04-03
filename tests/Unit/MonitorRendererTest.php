<?php

use App\Dto\Command;
use App\Dto\Deployment;
use App\Enums\CommandStatus;
use App\Prompts\MonitorCommand;
use App\Prompts\MonitorCommandRenderer;
use App\Prompts\MonitorDeployments;
use App\Prompts\MonitorDeploymentsRenderer;

it('renders a command with null startedAt', function () {
    $command = Command::from([
        'id' => 'cmd-1',
        'command' => 'php artisan migrate',
        'status' => CommandStatus::SUCCESS,
        'output' => 'Migration complete',
        'exitCode' => 0,
        'startedAt' => null,
        'finishedAt' => null,
    ]);

    $monitor = new MonitorCommand(
        getCommand: fn () => null,
        command: null,
    );
    $monitor->lastCommand = $command;

    $renderer = new MonitorCommandRenderer($monitor);

    expect($renderer($monitor))->not->toBeNull();
});

it('renders a deployment with null startedAt', function () {
    $deployment = Deployment::from([
        'id' => 'deploy-1',
        'status' => 'deployment.succeeded',
        'commitMessage' => 'Initial commit',
        'commitAuthor' => 'Test User',
        'startedAt' => null,
        'finishedAt' => null,
    ]);

    $monitor = new MonitorDeployments(
        getDeployment: fn () => null,
    );
    $monitor->lastDeployment = $deployment;

    $renderer = new MonitorDeploymentsRenderer($monitor);

    expect($renderer($monitor))->not->toBeNull();
});
