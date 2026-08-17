<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use App\Git;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    $this->repoRoot = sys_get_temp_dir().'/cloud-cli-repo-config-'.uniqid();
    File::ensureDirectoryExists($this->repoRoot);

    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('isRepo')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('getRoot')->andReturn($this->repoRoot)->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']))->byDefault();
    $this->mockConfig->shouldReceive('setApiTokens')->byDefault();
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

/**
 * Two tokens, one per organization — which is what having multiple organizations means
 * to the CLI. Both the organization and the applications a request sees depend on the
 * token it was signed with, exactly as the API scopes them.
 */
function fakeTwoOrganizations(): void
{
    test()->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['token-acme', 'token-globex']));

    $signedWithAcme = fn (PendingRequest $request) => str_contains($request->headers()->get('Authorization') ?? '', 'token-acme');

    MockClient::global([
        GetOrganizationRequest::class => function (PendingRequest $request) use ($signedWithAcme) {
            $isAcme = $signedWithAcme($request);

            return MockResponse::make([
                'data' => [
                    'id' => $isAcme ? 'org-acme' : 'org-globex',
                    'type' => 'organizations',
                    'attributes' => [
                        'name' => $isAcme ? 'Acme' : 'Globex',
                        'slug' => $isAcme ? 'acme' : 'globex',
                    ],
                ],
            ], 200);
        },
        ListApplicationsRequest::class => function (PendingRequest $request) use ($signedWithAcme) {
            $isAcme = $signedWithAcme($request);
            $organizationId = $isAcme ? 'org-acme' : 'org-globex';

            return MockResponse::make([
                'data' => [
                    createApplicationResponse([
                        'id' => $isAcme ? 'app-acme' : 'app-globex',
                        'attributes' => ['name' => $isAcme ? 'Acme App' : 'Globex App'],
                        'relationships' => [
                            'organization' => ['data' => ['id' => $organizationId, 'type' => 'organizations']],
                        ],
                    ]),
                ],
                'included' => [
                    ['id' => $organizationId, 'type' => 'organizations', 'attributes' => ['name' => $isAcme ? 'Acme' : 'Globex', 'slug' => $isAcme ? 'acme' : 'globex']],
                    ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
                ],
                'links' => ['next' => null],
            ], 200);
        },
    ]);
}

afterEach(function () {
    File::deleteDirectory($this->repoRoot);

    MockClient::destroyGlobal();
});

it('saves the repository defaults for the application named in the argument', function () {
    setupApplicationListMocks([
        createApplicationResponse(['id' => 'app-123', 'attributes' => ['name' => 'My App']]),
        createApplicationResponse(['id' => 'app-456', 'attributes' => ['name' => 'Other App']]),
    ]);

    $exitCode = Artisan::call('repo:config', ['application' => 'Other App', '--no-interaction' => true]);

    expect($exitCode)->toBe(0);
    expect(File::json($this->repoRoot.'/.cloud/config.json'))->toMatchArray([
        'organization_id' => 'org-1',
        'application_id' => 'app-456',
    ]);
});

it('saves the repository defaults without an argument when the organization has one application', function () {
    setupApplicationListMocks();

    $exitCode = Artisan::call('repo:config', ['--no-interaction' => true]);

    expect($exitCode)->toBe(0);
    expect(File::json($this->repoRoot.'/.cloud/config.json'))->toMatchArray([
        'application_id' => 'app-123',
    ]);
});

it('fails non-interactively when multiple applications exist and no argument is given', function () {
    setupApplicationListMocks([
        createApplicationResponse(['id' => 'app-123', 'attributes' => ['name' => 'My App']]),
        createApplicationResponse(['id' => 'app-456', 'attributes' => ['name' => 'Other App']]),
    ]);

    $exitCode = Artisan::call('repo:config', ['--no-interaction' => true]);

    expect($exitCode)->toBe(1);
    expect(File::exists($this->repoRoot.'/.cloud/config.json'))->toBeFalse();
});

it('fails when the application argument matches nothing', function () {
    setupApplicationListMocks();

    $exitCode = Artisan::call('repo:config', ['application' => 'nonexistent', '--no-interaction' => true]);

    expect($exitCode)->toBe(1);
    expect(File::exists($this->repoRoot.'/.cloud/config.json'))->toBeFalse();
});

it('fails when there are no applications', function () {
    setupApplicationListMocks([]);

    $exitCode = Artisan::call('repo:config', ['--no-interaction' => true]);

    expect($exitCode)->toBe(1);
    expect(File::exists($this->repoRoot.'/.cloud/config.json'))->toBeFalse();
});

it('picks the organization named in --organization, and an application from it', function (string $identifier) {
    fakeTwoOrganizations();

    $exitCode = Artisan::call('repo:config', ['--organization' => $identifier, '--no-interaction' => true]);

    expect($exitCode)->toBe(0);
    expect(File::json($this->repoRoot.'/.cloud/config.json'))->toMatchArray([
        'organization_id' => 'org-globex',
        'application_id' => 'app-globex',
    ]);
})->with(['Globex', 'globex', 'org-globex']);

it('will not save an application belonging to another organization', function () {
    fakeTwoOrganizations();

    $exitCode = Artisan::call('repo:config', [
        'application' => 'Acme App',
        '--organization' => 'Globex',
        '--no-interaction' => true,
    ]);

    expect($exitCode)->toBe(1);
    expect(File::exists($this->repoRoot.'/.cloud/config.json'))->toBeFalse();
});

it('fails when --organization matches none of the organizations', function () {
    fakeTwoOrganizations();

    $exitCode = Artisan::call('repo:config', ['--organization' => 'Initech', '--no-interaction' => true]);

    expect($exitCode)->toBe(1);
    expect(File::exists($this->repoRoot.'/.cloud/config.json'))->toBeFalse();
});

it('fails non-interactively with multiple organizations and no --organization', function () {
    fakeTwoOrganizations();

    $exitCode = Artisan::call('repo:config', ['--no-interaction' => true]);

    expect($exitCode)->toBe(1);
    expect(File::exists($this->repoRoot.'/.cloud/config.json'))->toBeFalse();
});

it('fails when --organization does not match the only token', function () {
    setupApplicationListMocks();

    $exitCode = Artisan::call('repo:config', ['--organization' => 'Initech', '--no-interaction' => true]);

    expect($exitCode)->toBe(1);
    expect(File::exists($this->repoRoot.'/.cloud/config.json'))->toBeFalse();
});
