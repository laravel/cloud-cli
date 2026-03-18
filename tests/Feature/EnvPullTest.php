<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
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

function setupEnvPullMocks(array $envVars = []): void
{
    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [createApplicationResponse()],
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org']],
                createEnvironmentResponse(['attributes' => ['environment_variables' => $envVars]]),
            ],
            'links' => ['next' => null],
        ], 200),

        ListEnvironmentsRequest::class => MockResponse::make([
            'data' => [createEnvironmentResponse(['attributes' => ['environment_variables' => $envVars]])],
            'links' => ['next' => null],
        ], 200),

        GetEnvironmentRequest::class => MockResponse::make([
            'data' => createEnvironmentResponse(['attributes' => ['environment_variables' => $envVars]]),
        ], 200),
    ]);
}

it('pulls environment variables to a file', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    $outputFile = tempnam(sys_get_temp_dir(), 'env_pull_test_');

    setupEnvPullMocks([
        ['key' => 'APP_NAME', 'value' => 'MyApp'],
        ['key' => 'APP_KEY', 'value' => 'base64:abc123'],
    ]);

    $this->artisan('env:pull', [
        'environment' => 'production',
        '--output' => $outputFile,
    ])->assertSuccessful();

    $content = file_get_contents($outputFile);
    expect($content)->toContain('APP_NAME=MyApp');
    expect($content)->toContain('APP_KEY=base64:abc123');

    unlink($outputFile);
});

it('outputs json with --json flag', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupEnvPullMocks([
        ['key' => 'APP_NAME', 'value' => 'MyApp'],
        ['key' => 'DB_HOST', 'value' => 'localhost'],
    ]);

    $this->artisan('env:pull', [
        'environment' => 'production',
        '--json' => true,
    ])
        ->expectsOutputToContain('"APP_NAME":"MyApp"')
        ->assertSuccessful();
});

it('handles empty environment variables', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    setupEnvPullMocks([]);

    $this->artisan('env:pull', ['environment' => 'production'])
        ->assertSuccessful();
});

it('quotes values with spaces in output file', function () {
    Prompt::fake();

    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app');

    $outputFile = tempnam(sys_get_temp_dir(), 'env_pull_test_');

    setupEnvPullMocks([
        ['key' => 'APP_NAME', 'value' => 'My Application'],
    ]);

    $this->artisan('env:pull', [
        'environment' => 'production',
        '--output' => $outputFile,
    ])->assertSuccessful();

    $content = file_get_contents($outputFile);
    expect($content)->toContain('APP_NAME="My Application"');

    unlink($outputFile);
});
