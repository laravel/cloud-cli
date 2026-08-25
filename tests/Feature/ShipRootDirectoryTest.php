<?php

use App\Client\Resources\Applications\CreateApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use App\Git;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('isRepo')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('getRoot')->andReturn('/tmp/test-repo')->byDefault();
    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app')->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function setupShipCreateMocks(): void
{
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [],
            'included' => [],
            'links' => ['next' => null],
        ], 200),
        CreateApplicationRequest::class => MockResponse::make([
            'data' => createApplicationResponse(),
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
                ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
            ],
        ], 200),
    ]);
}

function runShipUntilItLeavesTheMockedFlow(array $options): void
{
    try {
        Artisan::call('ship', [...$options, '--no-interaction' => true]);
    } catch (Throwable) {
        // Only the creation step is mocked. The rest of the ship flow is out of scope here.
    }
}

it('sends the root directory when shipping with the option', function () {
    setupShipCreateMocks();

    runShipUntilItLeavesTheMockedFlow([
        '--name' => 'My App',
        '--region' => 'us-east-1',
        '--root-directory' => 'backend',
    ]);

    MockClient::global()->assertSent(function ($request) {
        return $request instanceof CreateApplicationRequest
            && $request->body()->all()['root_directory'] === 'backend';
    });
});

it('omits the root directory when shipping without the option', function () {
    setupShipCreateMocks();

    runShipUntilItLeavesTheMockedFlow([
        '--name' => 'My App',
        '--region' => 'us-east-1',
    ]);

    MockClient::global()->assertSent(function ($request) {
        return $request instanceof CreateApplicationRequest
            && ! array_key_exists('root_directory', $request->body()->all());
    });
});
