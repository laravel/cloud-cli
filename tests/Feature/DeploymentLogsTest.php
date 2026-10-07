<?php

use App\Client\Resources\Deployments\GetDeploymentLogsRequest;
use App\Client\Resources\Deployments\GetDeploymentRequest;
use App\ConfigRepository;
use App\Dto\DeploymentLogStep;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function deploymentLogsResponse(): array
{
    return [
        'data' => [
            'build' => [
                'available' => true,
                'steps' => [
                    ['step' => 'clone', 'status' => 'finished', 'description' => 'Cloning repository', 'duration_ms' => 1500, 'time' => '00:00:02'],
                    ['step' => 'build', 'status' => 'failed', 'description' => 'Running build commands', 'output' => "npm ERR! missing script: build\n", 'duration_ms' => 320],
                    ['step' => 'push', 'status' => 'cancelled', 'description' => 'Pushing image'],
                ],
            ],
            'deploy' => [
                'available' => false,
                'steps' => [],
            ],
        ],
        'meta' => [
            'deployment_status' => 'build.failed',
        ],
    ];
}

function mockDeploymentLogs(MockResponse $logs): MockClient
{
    return MockClient::global([
        GetDeploymentRequest::class => MockResponse::make([
            'data' => [
                'id' => 'depl-123',
                'type' => 'deployments',
                'attributes' => ['status' => 'build.failed'],
            ],
        ], 200),
        GetDeploymentLogsRequest::class => $logs,
    ]);
}

it('fetches the logs for the deployment', function () {
    $mockClient = mockDeploymentLogs(MockResponse::make(deploymentLogsResponse(), 200));

    $exitCode = Artisan::call('deployment:logs', ['deployment' => 'depl-123', '--json' => true]);

    expect($exitCode)->toBe(0);
    expect($mockClient->getLastPendingRequest()->getUrl())->toEndWith('/deployments/depl-123/logs');
});

it('outputs each phase and its steps as JSON', function () {
    mockDeploymentLogs(MockResponse::make(deploymentLogsResponse(), 200));

    Artisan::call('deployment:logs', ['deployment' => 'depl-123', '--json' => true]);

    $decoded = json_decode(Artisan::output(), true);

    expect($decoded['deploymentStatus'])->toBe('build.failed');
    expect($decoded['build']['available'])->toBeTrue();
    expect($decoded['build']['steps'])->toHaveCount(3);
    expect($decoded['build']['steps'][1])->toMatchArray([
        'step' => 'build',
        'status' => 'failed',
        'description' => 'Running build commands',
        'output' => "npm ERR! missing script: build\n",
        'durationMs' => 320,
        'time' => null,
    ]);
    expect($decoded['deploy'])->toBe(['available' => false, 'steps' => []]);
});

it('filters the JSON output with --fields', function () {
    mockDeploymentLogs(MockResponse::make(deploymentLogsResponse(), 200));

    Artisan::call('deployment:logs', ['deployment' => 'depl-123', '--json' => true, '--fields' => 'deploymentStatus']);

    expect(json_decode(Artisan::output(), true))->toBe(['deploymentStatus' => 'build.failed']);
});

it('fails when the API rejects the request', function () {
    mockDeploymentLogs(MockResponse::make([
        'message' => 'Deployment logs are only available for deployments created within the last year.',
    ], 422));

    $exitCode = Artisan::call('deployment:logs', ['deployment' => 'depl-123', '--json' => true]);

    expect($exitCode)->toBe(1);
    expect(json_decode(Artisan::output(), true))->toBe([
        'message' => 'Deployment logs are only available for deployments created within the last year.',
    ]);
});

it('formats step durations', function (?int $ms, ?string $expected) {
    $step = new DeploymentLogStep(step: 'build', status: 'finished', description: 'Build', durationMs: $ms);

    expect($step->formattedDuration())->toBe($expected);
})->with([
    [null, null],
    [-2000, null],
    [320, '320ms'],
    [12000, '12s'],
    [125000, '2m 5s'],
]);
