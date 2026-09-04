<?php

use App\Client\Requests\CreateApplicationRequestData;
use App\Client\Resources\Applications\CreateApplicationRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Meta\ListRegionsRequest;
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
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('laravel/cloud-cli')->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function setupApplicationCreateMocks(array $attributeOverrides = [], ?MockResponse $createResponse = null): void
{
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListRegionsRequest::class => MockResponse::make(regionsResponse(), 200),
        CreateApplicationRequest::class => $createResponse ?? MockResponse::make([
            'data' => createApplicationResponse(['attributes' => $attributeOverrides]),
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
                ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
            ],
        ], 200),
    ]);
}

it('sends the root directory when the option is passed', function (string $rootDirectory) {
    setupApplicationCreateMocks(['root_directory' => $rootDirectory]);

    $this->artisan('application:create', [
        '--name' => 'My App',
        '--repository' => 'user/my-app',
        '--region' => 'us-east-1',
        '--root-directory' => $rootDirectory,
        '--no-interaction' => true,
    ])->assertSuccessful();

    MockClient::global()->assertSent(function ($request) use ($rootDirectory) {
        return $request instanceof CreateApplicationRequest
            && $request->body()->all()['root_directory'] === $rootDirectory;
    });
})->with(['backend', 'apps/api']);

it('omits the root directory from the request body when the option is not passed', function () {
    setupApplicationCreateMocks();

    $this->artisan('application:create', [
        '--name' => 'My App',
        '--repository' => 'user/my-app',
        '--region' => 'us-east-1',
        '--no-interaction' => true,
    ])->assertSuccessful();

    MockClient::global()->assertSent(function ($request) {
        return $request instanceof CreateApplicationRequest
            && ! array_key_exists('root_directory', $request->body()->all());
    });
});

it('outputs the root directory in the JSON representation', function () {
    setupApplicationCreateMocks(['root_directory' => 'backend']);

    $exitCode = Artisan::call('application:create', [
        '--name' => 'My App',
        '--repository' => 'user/my-app',
        '--region' => 'us-east-1',
        '--root-directory' => 'backend',
        '--json' => true,
        '--no-interaction' => true,
    ]);

    expect($exitCode)->toBe(0);

    $decoded = json_decode(Artisan::output(), true);

    expect($decoded)->toBeArray()
        ->and($decoded['rootDirectory'])->toBe('backend');
});

it('parses responses without a root directory attribute as null', function () {
    setupApplicationCreateMocks();

    $exitCode = Artisan::call('application:create', [
        '--name' => 'My App',
        '--repository' => 'user/my-app',
        '--region' => 'us-east-1',
        '--json' => true,
        '--no-interaction' => true,
    ]);

    expect($exitCode)->toBe(0);

    $decoded = json_decode(Artisan::output(), true);

    expect($decoded)->toBeArray()
        ->and($decoded['rootDirectory'])->toBeNull();
});

it('fails when the API rejects the root directory', function () {
    setupApplicationCreateMocks(createResponse: MockResponse::make([
        'message' => 'Validation failed',
        'errors' => ['root_directory' => ['The selected directory is invalid.']],
    ], 422));

    $this->artisan('application:create', [
        '--name' => 'My App',
        '--repository' => 'user/my-app',
        '--region' => 'us-east-1',
        '--root-directory' => '/invalid/',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('strips a trailing slash from the root directory', function (?string $given, ?string $expected) {
    $data = new CreateApplicationRequestData(
        repository: 'user/my-app',
        name: 'My App',
        region: 'us-east-1',
        rootDirectory: $given,
    );

    expect($data->rootDirectory)->toBe($expected);
})->with([
    ['backend', 'backend'],
    ['backend/', 'backend'],
    ['apps/api/', 'apps/api'],
    ['/', null],
    ['', null],
    [null, null],
]);
