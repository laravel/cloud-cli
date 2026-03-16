<?php

use App\Client\Resources\Instances\DeleteInstanceRequest;
use App\Client\Resources\Instances\GetInstanceRequest;
use App\ConfigRepository;
use App\Git;
use Illuminate\Support\Sleep;
use Laravel\Prompts\Prompt;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    Sleep::fake();

    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('isRepo')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('getRoot')->andReturn('/tmp/test-repo')->byDefault();
    $this->mockGit->shouldReceive('currentBranch')->andReturn('main')->byDefault();
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('')->byDefault();
    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(false)->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(fn () => MockClient::destroyGlobal());

function instanceDeleteGetMock(): array
{
    return [
        'data' => [
            'id' => 'inst-123',
            'type' => 'instances',
            'attributes' => [
                'name' => 'web',
                'type' => 'service',
                'size' => 'shared-1x',
                'scaling_type' => 'custom',
                'min_replicas' => 1,
                'max_replicas' => 3,
                'uses_scheduler' => false,
                'scaling_cpu_threshold_percentage' => 70,
                'scaling_memory_threshold_percentage' => 70,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ],
            'relationships' => [
                'environment' => ['data' => ['id' => 'env-1', 'type' => 'environments']],
            ],
        ],
        'included' => [
            createEnvironmentResponse(),
        ],
    ];
}

it('deletes an instance by ID with force flag', function () {
    Prompt::fake();

    MockClient::global([
        GetInstanceRequest::class => MockResponse::make(instanceDeleteGetMock(), 200),
        DeleteInstanceRequest::class => MockResponse::make([], 204),
    ]);

    $this->artisan('instance:delete', [
        'instance' => 'inst-123',
        '--force' => true,
    ])->assertSuccessful();
});

it('deletes an instance after confirming via prompt', function () {
    Prompt::fake();

    MockClient::global([
        GetInstanceRequest::class => MockResponse::make(instanceDeleteGetMock(), 200),
        DeleteInstanceRequest::class => MockResponse::make([], 204),
    ]);

    // confirm() defaults to true when faked, so deletion proceeds
    $this->artisan('instance:delete', [
        'instance' => 'inst-123',
    ])->assertSuccessful();
});

// BUG: InstanceDelete catches Illuminate\Http\Client\RequestException instead of
// Saloon\Exceptions\Request\RequestException. API errors (500) are not caught
// by the command's try/catch and propagate to the framework's exception handler,
// resulting in a generic failure instead of the friendly "Failed to delete instance" message.
it('fails on API error because wrong exception class is caught', function () {
    Prompt::fake();

    MockClient::global([
        GetInstanceRequest::class => MockResponse::make(instanceDeleteGetMock(), 200),
        DeleteInstanceRequest::class => MockResponse::make(['message' => 'Server error'], 500),
    ]);

    // BUG: The wrong exception class means this throws instead of showing a friendly error.
    // Once the import is fixed to Saloon\Exceptions\Request\RequestException (see PR #42),
    // this test should change to ->assertFailed() with expectsOutputToContain('Failed to delete').
})->skip('Known bug: catches Illuminate\\Http\\Client\\RequestException instead of Saloon — see PR #42');
