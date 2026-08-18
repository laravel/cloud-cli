<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Cloud;
use App\Concerns\HasAClient;
use App\ConfigRepository;
use App\Support\Stdin;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['config-token']))->byDefault();
    $this->mockConfig->shouldReceive('path')->andReturn('/tmp/cloud-config.json')->byDefault();
    $this->mockConfig->shouldReceive('addApiToken')->byDefault();
    $this->mockConfig->shouldReceive('removeApiToken')->byDefault();
    $this->mockConfig->shouldReceive('setApiTokens')->byDefault();
    $this->app->instance(ConfigRepository::class, $this->mockConfig);

    $this->mockStdin = Mockery::mock(Stdin::class);
    $this->mockStdin->shouldReceive('read')->andReturn(null)->byDefault();
    $this->app->instance(Stdin::class, $this->mockStdin);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function pipeToStdin(?string $value): void
{
    test()->mockStdin->shouldReceive('read')->andReturn($value);
}

/** Resolves a token straight from the trait, where the organization check lives. */
function resolveApiTokenFor(?string $organization = null): string
{
    return (new class
    {
        use HasAClient;

        public function resolve(?string $organization): string
        {
            return $this->resolveApiToken(organization: $organization);
        }
    })->resolve($organization);
}

/** Records the bearer token every request was signed with. */
function recordSigningTokens(string $organizationName = 'My Org'): Closure
{
    $seen = collect();

    MockClient::global([
        GetOrganizationRequest::class => function (PendingRequest $request) use ($seen, $organizationName) {
            $seen->push(str_replace('Bearer ', '', $request->headers()->get('Authorization') ?? ''));

            return MockResponse::make([
                'data' => [
                    'id' => 'org-1',
                    'type' => 'organizations',
                    'attributes' => ['name' => $organizationName, 'slug' => 'my-org'],
                ],
            ], 200);
        },
        ListApplicationsRequest::class => function (PendingRequest $request) use ($seen) {
            $seen->push(str_replace('Bearer ', '', $request->headers()->get('Authorization') ?? ''));

            return MockResponse::make([
                'data' => [createApplicationResponse()],
                'included' => [
                    ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
                    ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
                ],
                'links' => ['next' => null],
            ], 200);
        },
    ]);

    return fn () => $seen;
}

it('reads the api token from the environment', function () {
    config()->set('cloud.api_token', 'env-token');

    expect(Cloud::apiTokenFromEnvironment())->toBe('env-token');
});

it('treats a blank environment token as unset so it can be bypassed per command', function (?string $value) {
    config()->set('cloud.api_token', $value);

    expect(Cloud::apiTokenFromEnvironment())->toBeNull();
})->with([null, '', '   ']);

it('trims surrounding whitespace from the environment token', function () {
    config()->set('cloud.api_token', "  env-token\n");

    expect(Cloud::apiTokenFromEnvironment())->toBe('env-token');
});

it('signs requests with the environment token in preference to the saved token', function () {
    config()->set('cloud.api_token', 'env-token');
    $tokens = recordSigningTokens();

    Artisan::call('application:list', ['--json' => true, '--no-interaction' => true]);

    expect($tokens()->unique()->values()->all())->toBe(['env-token']);
});

it('falls back to the saved token when no environment token is set', function () {
    config()->set('cloud.api_token', null);
    $tokens = recordSigningTokens();

    Artisan::call('application:list', ['--json' => true, '--no-interaction' => true]);

    expect($tokens()->unique()->values()->all())->toBe(['config-token']);
});

it('authenticates from the environment when no token is saved', function () {
    config()->set('cloud.api_token', 'env-token');
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect());
    $tokens = recordSigningTokens();

    Artisan::call('application:list', ['--json' => true, '--no-interaction' => true]);

    expect($tokens()->unique()->values()->all())->toBe(['env-token']);
});

it('rejects an explicitly named organization the environment token does not belong to', function () {
    config()->set('cloud.api_token', 'env-token');
    recordSigningTokens(organizationName: 'Acme');

    expect(fn () => resolveApiTokenFor('globex'))
        ->toThrow(RuntimeException::class, 'The API token in LARAVEL_CLOUD_TOKEN belongs to [Acme], not [globex].');
});

it('accepts an explicitly named organization the environment token belongs to', function (string $organization) {
    config()->set('cloud.api_token', 'env-token');
    recordSigningTokens(organizationName: 'Acme');

    expect(resolveApiTokenFor($organization))->toBe('env-token');
})->with(['org-1', 'Acme', 'my-org']);

it('does not check the organization when none is named', function () {
    config()->set('cloud.api_token', 'env-token');
    $tokens = recordSigningTokens();

    expect(resolveApiTokenFor())->toBe('env-token');
    expect($tokens()->all())->toBeEmpty();
});

it('reports a rejected environment token against the environment variable', function () {
    config()->set('cloud.api_token', 'env-token');

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(['message' => 'Unauthenticated.'], 401),
    ]);

    expect(fn () => resolveApiTokenFor('acme'))
        ->toThrow(RuntimeException::class, 'The API token in LARAVEL_CLOUD_TOKEN was rejected.');
});

it('adds a token given with the token option', function () {
    $this->mockConfig->shouldReceive('addApiToken')->once()->with('new-token');

    Artisan::call('auth:token', ['--add' => true, '--token' => 'new-token', '--no-interaction' => true]);
});

it('adds a token piped to stdin', function () {
    pipeToStdin("new-token\n");
    $this->mockConfig->shouldReceive('addApiToken')->once()->with('new-token');

    Artisan::call('auth:token', ['--add' => true, '--no-interaction' => true]);
});

it('prefers the token option over stdin', function () {
    pipeToStdin('piped-token');
    $this->mockConfig->shouldReceive('addApiToken')->once()->with('flag-token');

    Artisan::call('auth:token', ['--add' => true, '--token' => 'flag-token', '--no-interaction' => true]);
});

it('does not save a token twice', function () {
    $this->mockConfig->shouldReceive('addApiToken')->never();

    Artisan::call('auth:token', ['--add' => true, '--token' => 'config-token', '--no-interaction' => true]);
});

it('explains how to supply a token when adding one non-interactively with no input', function () {
    $this->mockConfig->shouldReceive('addApiToken')->never();

    $exitCode = Artisan::call('auth:token', ['--add' => true, '--no-interaction' => true]);

    expect($exitCode)->toBe(1);
});

it('still saves a token when the environment token is set', function () {
    config()->set('cloud.api_token', 'env-token');
    $this->mockConfig->shouldReceive('addApiToken')->once()->with('new-token');

    $exitCode = Artisan::call('auth:token', ['--add' => true, '--token' => 'new-token', '--no-interaction' => true]);

    expect($exitCode)->toBe(0);
});

it('removes a token given with the token option', function () {
    $this->mockConfig->shouldReceive('removeApiToken')->once()->with('config-token');

    Artisan::call('auth:token', ['--remove' => true, '--token' => 'config-token', '--no-interaction' => true]);
});

it('refuses to remove a token that is not saved', function () {
    $this->mockConfig->shouldReceive('removeApiToken')->never();

    $exitCode = Artisan::call('auth:token', ['--remove' => true, '--token' => 'unknown-token', '--no-interaction' => true]);

    expect($exitCode)->toBe(1);
});

it('lists the environment token alongside saved tokens and names each source', function () {
    config()->set('cloud.api_token', 'env-token');
    recordSigningTokens();

    Artisan::call('auth:token', ['--list' => true, '--no-interaction' => true]);

    $listed = collect(json_decode(Artisan::output(), true));

    expect($listed->pluck('token')->all())->toBe(['env-token', 'config-token']);
    expect($listed->pluck('source')->all())->toBe(['LARAVEL_CLOUD_TOKEN', '/tmp/cloud-config.json']);
});

it('names an action when none is given non-interactively', function () {
    $exitCode = Artisan::call('auth:token', ['--no-interaction' => true]);

    expect($exitCode)->toBe(1);
});
