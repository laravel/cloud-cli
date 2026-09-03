<?php

use App\Client\Resources\Applications\CreateApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Commands\Ship;
use App\ConfigRepository;
use App\Git;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Symfony\Component\Console\Input\ArrayInput;

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

    foreach (temporaryMonorepos() as $path) {
        File::deleteDirectory($path);
    }

    temporaryMonorepos(reset: true);
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

/**
 * @return array<int, string>
 */
function temporaryMonorepos(?string $add = null, bool $reset = false): array
{
    static $paths = [];

    if ($reset) {
        $paths = [];
    }

    if ($add !== null) {
        $paths[] = $add;
    }

    return $paths;
}

function temporaryMonorepo(?array $composerRequire = null): string
{
    $repository = sys_get_temp_dir().'/ship-monorepo-'.uniqid();

    temporaryMonorepos(add: $repository);

    File::ensureDirectoryExists($repository.'/backend');

    if ($composerRequire !== null) {
        File::put($repository.'/backend/composer.json', json_encode(['require' => $composerRequire]));
    }

    return $repository;
}

function shipCommandWithRootDirectory(?string $rootDirectory, ?string $gitRoot): Ship
{
    $command = app(Ship::class);

    $input = new ArrayInput(
        $rootDirectory === null ? [] : ['--root-directory' => $rootDirectory],
        $command->getDefinition(),
    );

    $git = Mockery::mock(Git::class);
    $git->shouldReceive('getRoot')->andReturn($gitRoot)->byDefault();

    (function () use ($input, $git) {
        $this->input = $input;
        $this->git = $git;
    })->call($command);

    return $command;
}

it('resolves the project path against the repository root when a root directory is given', function () {
    $command = shipCommandWithRootDirectory('backend', '/tmp/test-repo');

    expect((fn () => $this->projectPath())->call($command))->toBe('/tmp/test-repo/backend');
});

it('resolves the project path to the working directory without a root directory', function () {
    $command = shipCommandWithRootDirectory(null, '/tmp/test-repo');

    expect((fn () => $this->projectPath())->call($command))->toBe(getcwd());
});

it('reads composer.json from the root directory rather than the working directory', function () {
    $repository = temporaryMonorepo(['laravel/octane' => '^2.0']);

    $command = shipCommandWithRootDirectory('backend', $repository);

    $composer = (fn () => $this->composer())->call($command);

    expect($composer)->not->toBeNull()
        ->and($composer->hasPackage('laravel/octane'))->toBeTrue();
});

it('skips package detection instead of crashing when the project has no composer.json', function () {
    $repository = temporaryMonorepo();

    $command = shipCommandWithRootDirectory('backend', $repository);

    expect((fn () => $this->composer())->call($command))->toBeNull();
});

function listApplicationsResponseWith(?string $rootDirectory): array
{
    return [
        'data' => [createApplicationResponse([
            'id' => 'app-existing',
            'attributes' => ['root_directory' => $rootDirectory],
        ])],
        'included' => [
            ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
            ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
        ],
        'links' => ['next' => null],
    ];
}

it('creates a second application for a repository when it is rooted at another directory', function () {
    setupShipCreateMocks();

    MockClient::global()->addResponse(
        MockResponse::make(listApplicationsResponseWith('backend'), 200),
        ListApplicationsRequest::class,
    );

    runShipUntilItLeavesTheMockedFlow([
        '--name' => 'My App',
        '--region' => 'us-east-1',
        '--root-directory' => 'frontend',
    ]);

    MockClient::global()->assertSent(function ($request) {
        return $request instanceof CreateApplicationRequest
            && $request->body()->all()['root_directory'] === 'frontend';
    });
});

it('refuses to create a second application rooted at the same directory', function () {
    setupShipCreateMocks();

    MockClient::global()->addResponse(
        MockResponse::make(listApplicationsResponseWith('backend'), 200),
        ListApplicationsRequest::class,
    );

    $this->artisan('ship', [
        '--root-directory' => 'backend/',
        '--no-interaction' => true,
    ])->assertFailed();

    MockClient::global()->assertNotSent(CreateApplicationRequest::class);
});

it('ignores applications rooted in a subdirectory when shipping the repository root', function () {
    setupShipCreateMocks();

    MockClient::global()->addResponse(
        MockResponse::make(listApplicationsResponseWith('backend'), 200),
        ListApplicationsRequest::class,
    );

    runShipUntilItLeavesTheMockedFlow([
        '--name' => 'My App',
        '--region' => 'us-east-1',
    ]);

    MockClient::global()->assertSent(CreateApplicationRequest::class);
});

it('refuses to ship the repository root twice', function () {
    setupShipCreateMocks();

    MockClient::global()->addResponse(
        MockResponse::make(listApplicationsResponseWith(null), 200),
        ListApplicationsRequest::class,
    );

    $this->artisan('ship', ['--no-interaction' => true])->assertFailed();

    MockClient::global()->assertNotSent(CreateApplicationRequest::class);
});
