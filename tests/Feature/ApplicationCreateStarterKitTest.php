<?php

use App\Client\Resources\Applications\CreateApplicationRequest;
use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Meta\ListRegionsRequest;
use App\ConfigRepository;
use App\Git;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('isRepo')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('getRoot')->andReturn('/tmp/test-repo')->byDefault();
    $this->mockGit->shouldReceive('hasRemote')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('remoteHost')->andReturn('github.com')->byDefault();
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app')->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);

    $this->sentBody = null;

    $application = [
        'data' => createApplicationResponse(),
        'included' => [
            organizationResponse()['data'],
            createEnvironmentResponse(),
        ],
    ];

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListRegionsRequest::class => MockResponse::make(regionsResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make(['data' => [], 'included' => [], 'links' => ['next' => null]], 200),
        CreateApplicationRequest::class => function (PendingRequest $request) use ($application) {
            $this->sentBody = $request->body()?->all();

            return MockResponse::make($application, 200);
        },
        GetApplicationRequest::class => MockResponse::make($application, 200),
    ]);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

it('sends the starter kit with GitHub as the source provider', function () {
    $this->mockGit->shouldReceive('remoteHost')->andReturn('gitlab.com');

    $this->artisan('application:create', [
        '--name' => 'My App',
        '--repository' => 'user/new-app',
        '--region' => 'us-east-1',
        '--starter-kit' => 'laravel-react',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->sentBody)->toMatchArray([
        'repository' => 'user/new-app',
        'starter_kit' => 'laravel-react',
        'source_control_provider_type' => 'github',
    ]);
});

it('requires a repository instead of using the origin remote with a starter kit', function () {
    $this->artisan('application:create', [
        '--name' => 'My App',
        '--region' => 'us-east-1',
        '--starter-kit' => 'laravel',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($this->sentBody)->toBeNull();
});

it('rejects an unknown starter kit before calling the API', function () {
    $this->artisan('application:create', [
        '--name' => 'My App',
        '--repository' => 'user/new-app',
        '--region' => 'us-east-1',
        '--starter-kit' => 'symfony',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($this->sentBody)->toBeNull();
});

it('omits the starter kit when not given', function () {
    $this->artisan('application:create', [
        '--name' => 'My App',
        '--repository' => 'user/my-app',
        '--region' => 'us-east-1',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->sentBody)->not->toHaveKey('starter_kit');
});
