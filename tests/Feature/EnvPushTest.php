<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
use App\Client\Resources\Environments\ReplaceEnvironmentVariablesRequest;
use App\ConfigRepository;
use App\Git;
use Laravel\Prompts\Prompt;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('isRepo')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('getRoot')->andReturn('/tmp/test-repo')->byDefault();
    $this->mockGit->shouldReceive('currentBranch')->andReturn('main')->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function setupEnvPushMocks(array $existingVars = []): void
{
    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [createApplicationResponse()],
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org']],
                createEnvironmentResponse(['attributes' => ['environment_variables' => $existingVars]]),
            ],
            'links' => ['next' => null],
        ], 200),

        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [createEnvironmentResponse(['attributes' => ['environment_variables' => $existingVars]])],
            'links' => ['next' => null],
        ], 200),

        GetEnvironmentRequest::class => MockResponse::make([
            'data' => createEnvironmentResponse(['attributes' => ['environment_variables' => $existingVars]]),
        ], 200),

        ReplaceEnvironmentVariablesRequest::class => MockResponse::make([], 200),
    ]);
}

it('pushes env file with --force flag', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    $envFile = tempnam(sys_get_temp_dir(), 'env_push_test_');
    file_put_contents($envFile, "APP_NAME=MyApp\nAPP_KEY=base64:abc123\n");

    setupEnvPushMocks();

    $this->artisan('env:push', [
        'environment' => 'production',
        '--file' => $envFile,
        '--force' => true,
    ])->assertSuccessful();

    unlink($envFile);
});

it('fails when env file does not exist', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupEnvPushMocks();

    $this->artisan('env:push', [
        'environment' => 'production',
        '--file' => '/tmp/nonexistent-env-file-' . uniqid(),
        '--force' => true,
    ])->assertFailed();
});

it('fails without --force in non-interactive mode', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    $envFile = tempnam(sys_get_temp_dir(), 'env_push_test_');
    file_put_contents($envFile, "APP_NAME=MyApp\n");

    setupEnvPushMocks();

    $this->artisan('env:push', [
        'environment' => 'production',
        '--file' => $envFile,
        '--no-interaction' => true,
    ])->assertFailed();

    unlink($envFile);
});

it('parses env file with quoted values', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    $envFile = tempnam(sys_get_temp_dir(), 'env_push_test_');
    file_put_contents($envFile, "APP_NAME=\"My Application\"\nDB_PASSWORD='secret password'\n");

    setupEnvPushMocks();

    $this->artisan('env:push', [
        'environment' => 'production',
        '--file' => $envFile,
        '--force' => true,
    ])->assertSuccessful();

    unlink($envFile);
});
