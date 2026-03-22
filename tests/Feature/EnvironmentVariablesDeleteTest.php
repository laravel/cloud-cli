<?php

use App\Client\Resources\Environments\DeleteEnvironmentVariablesRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
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
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

it('deletes environment variables successfully in non-interactive mode', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetEnvironmentRequest::class => MockResponse::make([
            'data' => createEnvironmentResponse(),
        ], 200),
        DeleteEnvironmentVariablesRequest::class => MockResponse::make([], 200),
    ]);

    $this->artisan('environment:variables', [
        'environment' => 'env-1',
        '--action' => 'delete',
        '--key' => ['MY_VAR'],
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('fails when no keys are provided in non-interactive mode', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetEnvironmentRequest::class => MockResponse::make([
            'data' => createEnvironmentResponse(),
        ], 200),
    ]);

    $this->artisan('environment:variables', [
        'environment' => 'env-1',
        '--action' => 'delete',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('fails without force flag in non-interactive mode', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetEnvironmentRequest::class => MockResponse::make([
            'data' => createEnvironmentResponse(),
        ], 200),
    ]);

    $this->artisan('environment:variables', [
        'environment' => 'env-1',
        '--action' => 'delete',
        '--key' => ['MY_VAR'],
        '--no-interaction' => true,
    ])->assertFailed();
});
