<?php

use App\Client\Resources\Applications\CreateApplicationRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Meta\ListRegionsRequest;
use App\ConfigRepository;
use App\Git;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('isRepo')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('getRoot')->andReturn('/tmp/test-repo')->byDefault();
    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('laravel/cloud-cli')->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

it('fails cleanly when the API answers a successful request with something other than JSON', function () {
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListRegionsRequest::class => MockResponse::make(regionsResponse(), 200),
        CreateApplicationRequest::class => MockResponse::make('<!DOCTYPE html><html><body>Something went wrong</body></html>', 200),
    ]);

    $this->artisan('application:create', [
        '--name' => 'my-app',
        '--repository' => 'laravel/cloud-cli',
        '--region' => 'us-east-2',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('still reports a failed response as a request error rather than an unreadable one', function () {
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListRegionsRequest::class => MockResponse::make(regionsResponse(), 200),
        CreateApplicationRequest::class => MockResponse::make('<!DOCTYPE html><html><body>Server Error</body></html>', 500),
    ]);

    $this->artisan('application:create', [
        '--name' => 'my-app',
        '--repository' => 'laravel/cloud-cli',
        '--region' => 'us-east-2',
        '--no-interaction' => true,
    ])->assertFailed();
});
