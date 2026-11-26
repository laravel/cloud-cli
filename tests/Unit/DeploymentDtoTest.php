<?php

use App\Dto\Deployment;

it('maps the deployment attributes the API returns', function () {
    $deployment = Deployment::createFromResponse([
        'data' => [
            'id' => 'deploy-1',
            'type' => 'deployments',
            'attributes' => [
                'slug' => 'brave-otter',
                'status' => 'deployment.succeeded',
                'branch_name' => 'main',
                'commit_hash' => 'abc1234',
                'commit_message' => 'Fix bug',
                'commit_author' => 'Taylor',
                'failure_reason' => null,
                'php_major_version' => '8.4',
                'build_command' => null,
                'node_version' => '22',
                'uses_octane' => false,
                'uses_hibernation' => true,
                'hibernation_wake_up_interval' => 30,
                'started_at' => '2026-10-01T12:00:00.000000Z',
                'finished_at' => '2026-10-01T12:02:00.000000Z',
                'trigger' => null,
                'trigger_reference' => null,
            ],
        ],
    ]);

    expect($deployment->slug)->toBe('brave-otter')
        ->and($deployment->hibernationWakeUpInterval)->toBe(30)
        ->and($deployment->commitHash)->toBe('abc1234')
        ->and($deployment->commitMessage)->toBe('Fix bug')
        ->and($deployment->commitAuthor)->toBe('Taylor')
        ->and($deployment->trigger)->toBeNull()
        ->and($deployment->triggerReference)->toBeNull()
        ->and($deployment->toArray())->not->toHaveKeys(['createdAt', 'updatedAt']);
});

it('maps why Laravel Cloud started a deployment itself', function () {
    $deployment = Deployment::createFromResponse([
        'data' => [
            'id' => 'deploy-2',
            'type' => 'deployments',
            'attributes' => [
                'status' => 'deployment.succeeded',
                'trigger' => 'patch',
                'trigger_reference' => 'CVE-2026-12345',
            ],
        ],
    ]);

    expect($deployment->trigger)->toBe('patch')
        ->and($deployment->triggerReference)->toBe('CVE-2026-12345')
        ->and($deployment->toArray())->toMatchArray([
            'trigger' => 'patch',
            'triggerReference' => 'CVE-2026-12345',
        ]);
});
