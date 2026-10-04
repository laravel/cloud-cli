<?php

use App\Client\Resources\Deployments\GetDeploymentLogsRequest;
use App\Client\Resources\Deployments\GetDeploymentRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use App\Dto\DeploymentLog;
use App\Prompts\DeploymentLogsPrompt;
use App\Prompts\DeploymentLogsPromptRenderer;
use App\Prompts\Renderer;
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

function failedDeploymentResponse(): array
{
    return [
        'data' => [
            'id' => 'depl-123',
            'type' => 'deployments',
            'attributes' => [
                'status' => 'build.failed',
                'branch_name' => 'main',
                'commit' => ['hash' => 'abc123', 'message' => 'Break the build', 'author' => 'Taylor'],
                'started_at' => '2026-10-01T10:00:00.000000Z',
                'finished_at' => '2026-10-01T10:01:00.000000Z',
                'failure_reason' => 'step=php.command exit_code=1',
            ],
            'relationships' => [
                'environment' => ['data' => ['id' => 'env-1', 'type' => 'environments']],
            ],
        ],
        'included' => [createEnvironmentResponse()],
    ];
}

/**
 * Shape returned by GET /deployments/{deployment}/logs in the Cloud API.
 */
function deploymentLogsResponse(): array
{
    return [
        'data' => [
            'build' => [
                'available' => true,
                'steps' => [
                    ['step' => 'git.clone', 'status' => 'finished', 'description' => 'Cloning application source control repository', 'output' => 'Cloned main@abc123', 'duration_ms' => 1200, 'time' => '00:00:01'],
                    ['step' => 'php.command', 'status' => 'failed', 'description' => 'Running build commands', 'output' => "npm run build\r\nError: Cannot find module 'vite'", 'duration_ms' => 4500],
                    ['step' => 'image.upload', 'status' => 'cancelled', 'description' => 'Uploading application'],
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

function mockDeploymentLogs(): MockClient
{
    return MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetDeploymentRequest::class => MockResponse::make(failedDeploymentResponse(), 200),
        GetDeploymentLogsRequest::class => MockResponse::make(deploymentLogsResponse(), 200),
        GetEnvironmentRequest::class => MockResponse::make([
            'data' => createEnvironmentResponse([
                'relationships' => ['application' => ['data' => ['id' => 'app-123', 'type' => 'applications']]],
            ]),
            'included' => [createApplicationResponse()],
        ], 200),
    ]);
}

it('requests the deployment logs endpoint', function () {
    $mockClient = mockDeploymentLogs();

    expect(Artisan::call('deployment:logs', ['deployment' => 'depl-123', '--json' => true, '--no-interaction' => true]))->toBe(0);

    expect($mockClient->getLastPendingRequest()->getUrl())->toEndWith('/deployments/depl-123/logs');
});

it('outputs the build and deploy steps as camelCase JSON', function () {
    mockDeploymentLogs();

    Artisan::call('deployment:logs', ['deployment' => 'depl-123', '--json' => true, '--no-interaction' => true]);

    $payload = json_decode(Artisan::output(), true);

    expect($payload)->toMatchArray([
        'deploymentId' => 'depl-123',
        'deploymentStatus' => 'build.failed',
    ]);
    expect($payload['build']['available'])->toBeTrue();
    expect($payload['build']['steps'][1])->toBe([
        'step' => 'php.command',
        'status' => 'failed',
        'description' => 'Running build commands',
        'output' => "npm run build\r\nError: Cannot find module 'vite'",
        'durationMs' => 4500,
        'time' => null,
    ]);
    expect($payload['build']['steps'][2]['output'])->toBeNull();
    expect($payload['deploy'])->toBe(['available' => false, 'steps' => []]);
});

it('filters JSON output to the requested fields', function () {
    mockDeploymentLogs();

    Artisan::call('deployment:logs', [
        'deployment' => 'depl-123',
        '--json' => true,
        '--no-interaction' => true,
        '--fields' => 'build.steps.step,build.steps.status',
    ]);

    expect(json_decode(Artisan::output(), true))->toBe([
        'build' => ['steps' => [
            ['step' => 'git.clone', 'status' => 'finished'],
            ['step' => 'php.command', 'status' => 'failed'],
            ['step' => 'image.upload', 'status' => 'cancelled'],
        ]],
    ]);
});

it('renders each step with its name, status and output', function () {
    Renderer::$suppressOutput = false;

    $prompt = new DeploymentLogsPrompt(DeploymentLog::createFromResponse('depl-123', deploymentLogsResponse()));
    $output = preg_replace('/\e\[[0-9;]*m/', '', (string) (new DeploymentLogsPromptRenderer($prompt))($prompt));

    expect($output)
        ->toContain('Build')
        ->toContain('✔  Cloning application source control repository  finished · 1.2s')
        ->toContain('Cloned main@abc123')
        ->toContain('✘  Running build commands  failed · 4.5s')
        ->toContain("Error: Cannot find module 'vite'")
        ->toContain('▲  Uploading application  cancelled')
        ->toContain('Deploy')
        ->toContain('No deploy log available.')
        ->not->toContain("\r");
});
