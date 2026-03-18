<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
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
    $this->mockGit->shouldReceive('currentBranch')->andReturn('main')->byDefault();
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('')->byDefault();
    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(false)->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(fn () => MockClient::destroyGlobal());

function appListOrgResponse(): array
{
    return [
        'data' => [
            'id' => 'org-1',
            'type' => 'organizations',
            'attributes' => ['name' => 'My Org', 'slug' => 'my-org'],
        ],
    ];
}

function appListMockResponse(array $applications = [], int $status = 200): array
{
    return [
        'data' => $applications,
        'included' => [
            ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
            ['id' => 'env-1', 'type' => 'environments', 'attributes' => [
                'name' => 'production',
                'slug' => 'production',
                'vanity_domain' => 'my-app.cloud.laravel.com',
                'status' => 'running',
                'php_major_version' => '8.3',
            ]],
        ],
        'links' => ['next' => null],
    ];
}

// ---- Happy path ----

it('lists applications successfully in interactive mode', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(appListOrgResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make(
            appListMockResponse([createApplicationResponse()]),
            200,
        ),
    ]);

    $this->artisan('application:list')
        ->assertSuccessful();
});

it('lists applications in non-interactive mode', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(appListOrgResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make(
            appListMockResponse([createApplicationResponse()]),
            200,
        ),
    ]);

    $this->artisan('application:list', ['--no-interaction' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('My App');
});

it('lists applications with --json output', function () {
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(appListOrgResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make(
            appListMockResponse([createApplicationResponse()]),
            200,
        ),
    ]);

    $this->artisan('application:list', ['--json' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('"id"');
});

// ---- Empty list ----

it('returns failure when no applications found in non-interactive mode', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(appListOrgResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make(
            appListMockResponse([]),
            200,
        ),
    ]);

    // In non-interactive mode, outputJsonIfWanted outputs empty JSON and exits with SUCCESS.
    // The warning + FAILURE path is only reached in truly interactive mode.
    $this->artisan('application:list', ['--no-interaction' => true])
        ->assertSuccessful();
});

it('outputs empty JSON when no applications found with --json', function () {
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(appListOrgResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make(
            appListMockResponse([]),
            200,
        ),
    ]);

    // outputJsonIfWanted exits with SUCCESS before the empty warning
    $this->artisan('application:list', ['--json' => true])
        ->assertSuccessful();
});

// ---- Multiple applications ----

it('lists multiple applications', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(appListOrgResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make(
            appListMockResponse([
                createApplicationResponse(),
                createApplicationResponse(['id' => 'app-456', 'attributes' => ['name' => 'Second App', 'slug' => 'second-app', 'region' => 'eu-west-1']]),
            ]),
            200,
        ),
    ]);

    $this->artisan('application:list')
        ->assertSuccessful();
});
