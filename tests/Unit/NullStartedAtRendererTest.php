<?php

/**
 * Regression test for GitHub issue #108:
 * "Can't run commands whilst environment is hibernating"
 *
 * When a command or deployment has null startedAt (e.g. during hibernation),
 * the renderers must not crash with: "Argument #5 ($info) must be of type string, null given"
 */

use App\Dto\Command;
use App\Dto\Deployment;
use App\Enums\CommandStatus;
use App\Prompts\MonitorCommand;
use App\Prompts\MonitorCommandRenderer;
use App\Prompts\MonitorDeployments;
use App\Prompts\MonitorDeploymentsRenderer;

it('MonitorCommandRenderer handles null startedAt without crashing', function () {
    // Create a command with null startedAt (simulates hibernating environment)
    $command = Command::from([
        'id' => 'cmd-1',
        'command' => 'php artisan migrate',
        'status' => CommandStatus::SUCCESS,
        'output' => 'Migration complete',
        'exitCode' => 0,
        'startedAt' => null,
        'finishedAt' => null,
    ]);

    // Create a MonitorCommand prompt with lastCommand set
    $monitor = new MonitorCommand(
        getCommand: fn () => null,
        command: null,
    );
    $monitor->lastCommand = $command;

    // This should not throw a TypeError about $info being null
    $renderer = new MonitorCommandRenderer($monitor);
    $output = $renderer($monitor);

    expect($output)->not->toBeNull();
});

it('MonitorDeploymentsRenderer handles null startedAt without crashing', function () {
    // Create a deployment with null startedAt (simulates hibernating environment)
    $deployment = Deployment::from([
        'id' => 'deploy-1',
        'status' => 'deployment.succeeded',
        'commitMessage' => 'Initial commit',
        'commitAuthor' => 'Test User',
        'startedAt' => null,
        'finishedAt' => null,
    ]);

    // Create a MonitorDeployments prompt with lastDeployment set
    $monitor = new MonitorDeployments(
        getDeployment: fn () => null,
    );
    $monitor->lastDeployment = $deployment;

    // This should not throw a TypeError about $info being null
    $renderer = new MonitorDeploymentsRenderer($monitor);
    $output = $renderer($monitor);

    expect($output)->not->toBeNull();
});
