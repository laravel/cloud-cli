<?php

use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\UpdateEnvironmentRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use App\Dto\Environment;
use App\Git;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

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

function startCommandEnvironmentResponse(array $attributes = []): array
{
    return [
        'data' => createEnvironmentResponse([
            'attributes' => $attributes,
            'relationships' => ['application' => ['data' => ['id' => 'app-123', 'type' => 'applications']]],
        ]),
        'included' => [createApplicationResponse()],
    ];
}

/**
 * Fakes the environment endpoints and returns a reader for the last update payload.
 */
function captureEnvironmentUpdate(array $attributes = []): Closure
{
    $captured = new stdClass;

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetApplicationRequest::class => MockResponse::make(['data' => createApplicationResponse()], 200),
        GetEnvironmentRequest::class => MockResponse::make(startCommandEnvironmentResponse($attributes), 200),
        UpdateEnvironmentRequest::class => function (PendingRequest $request) use ($captured, $attributes) {
            $captured->body = $request->body()->all();

            return MockResponse::make(startCommandEnvironmentResponse($attributes), 200);
        },
    ]);

    return fn () => $captured->body ?? null;
}

it('sends the start command when updating an environment', function () {
    $body = captureEnvironmentUpdate(['start_command' => 'node .output/server/index.mjs']);

    $this->artisan('environment:update', [
        'environment' => 'env-1',
        '--start-command' => 'node .output/server/index.mjs',
        '--json' => true,
        '--fields' => 'startCommand',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('"startCommand":"node .output\/server\/index.mjs"');

    expect($body())->toBe(['start_command' => 'node .output/server/index.mjs']);
});

it('omits the start command when it is not passed', function () {
    $body = captureEnvironmentUpdate();

    $this->artisan('environment:update', [
        'environment' => 'env-1',
        '--build-command' => 'npm run build',
        '--json' => true,
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($body())->toBe(['build_command' => 'npm run build']);
});

it('includes the start command in environment JSON output', function () {
    captureEnvironmentUpdate([
        'build_command' => 'npm run build',
        'deploy_command' => 'php artisan migrate --force',
        'start_command' => 'php artisan octane:start',
    ]);

    $this->artisan('environment:get', [
        'environment' => 'env-1',
        '--json' => true,
        '--fields' => 'buildCommand,deployCommand,startCommand',
        '--no-interaction' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('"startCommand":"php artisan octane:start"');
});

it('returns a null start command when the API does not send one', function () {
    captureEnvironmentUpdate();

    $this->artisan('environment:get', [
        'environment' => 'env-1',
        '--json' => true,
        '--fields' => 'startCommand',
        '--no-interaction' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('"startCommand":null');
});

it('maps start_command as a sibling of the build and deploy commands', function () {
    $environment = Environment::createFromResponse(startCommandEnvironmentResponse([
        'build_command' => 'npm run build',
        'deploy_command' => 'php artisan migrate --force',
        'start_command' => 'php artisan octane:start',
    ]));

    expect($environment->buildCommand)->toBe('npm run build')
        ->and($environment->deployCommand)->toBe('php artisan migrate --force')
        ->and($environment->startCommand)->toBe('php artisan octane:start');
});
