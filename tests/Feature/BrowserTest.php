<?php

use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use App\Git;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('isRepo')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('hasRemote')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('getRoot')->andReturn('/tmp/test-repo')->byDefault();
    $this->mockGit->shouldReceive('currentBranch')->andReturn('main')->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);

    Process::fake();
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function browserEnvironmentResponse(?string $hostnameStatus): array
{
    if ($hostnameStatus === null) {
        return ['data' => createEnvironmentResponse(), 'included' => []];
    }

    return [
        'data' => createEnvironmentResponse([
            'relationships' => [
                'primaryDomain' => ['data' => ['id' => 'dom-1', 'type' => 'domains']],
            ],
        ]),
        'included' => [[
            'id' => 'dom-1',
            'type' => 'domains',
            'attributes' => [
                'name' => 'example.com',
                'type' => 'root',
                'hostname_status' => $hostnameStatus,
                'ssl_status' => 'verified',
                'origin_status' => 'verified',
            ],
        ]],
    ];
}

function mockBrowserApi(?string $hostnameStatus): void
{
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetApplicationRequest::class => MockResponse::make(['data' => createApplicationResponse()], 200),
        GetEnvironmentRequest::class => MockResponse::make(browserEnvironmentResponse($hostnameStatus), 200),
    ]);
}

function openedUrl(string $url): void
{
    Process::assertRan(fn (PendingProcess $process) => in_array($url, (array) $process->command, true));
}

it('opens the verified primary domain', function () {
    mockBrowserApi('verified');

    $this->artisan('browser', ['application' => 'app-123', 'environment' => 'env-1', '--no-interaction' => true])
        ->assertSuccessful();

    openedUrl('https://example.com');
});

it('falls back to the vanity domain while the primary domain is unverified', function () {
    mockBrowserApi('pending');

    $this->artisan('browser', ['application' => 'app-123', 'environment' => 'env-1', '--no-interaction' => true])
        ->assertSuccessful();

    openedUrl('https://my-app.cloud.laravel.com');
});

it('opens the vanity domain when there is no custom domain', function () {
    mockBrowserApi(null);

    $this->artisan('browser', ['application' => 'app-123', 'environment' => 'env-1', '--no-interaction' => true])
        ->assertSuccessful();

    openedUrl('https://my-app.cloud.laravel.com');
});
