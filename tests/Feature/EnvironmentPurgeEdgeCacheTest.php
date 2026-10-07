<?php

use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\PurgeEdgeCacheRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

/**
 * @return Closure(): stdClass the purge request that was sent, if any
 */
function setupPurgeEdgeCacheMocks(?MockResponse $response = null): Closure
{
    $sent = new stdClass;

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetEnvironmentRequest::class => MockResponse::make(['data' => createEnvironmentResponse()], 200),
        PurgeEdgeCacheRequest::class => function (PendingRequest $request) use ($sent, $response) {
            $sent->url = $request->getUrl();
            $sent->body = $request->body()?->all();

            return $response ?? MockResponse::make(['data' => createEnvironmentResponse()], 200);
        },
    ]);

    return fn () => $sent;
}

it('purges everything when given no path, prefix, or tag', function () {
    $sent = setupPurgeEdgeCacheMocks();

    $this->artisan('environment:purge-edge-cache', ['environment' => 'env-1', '--no-interaction' => true])
        ->assertSuccessful();

    expect($sent()->url)->toEndWith('/environments/env-1/purge-edge-cache');
    expect($sent()->body)->toBe([]);
});

it('purges a single target', function (string $option, string $value) {
    $sent = setupPurgeEdgeCacheMocks();

    $this->artisan('environment:purge-edge-cache', [
        'environment' => 'env-1',
        "--{$option}" => $value,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent()->body)->toBe([$option => $value]);
})->with([
    ['path', '/blog/hello-world'],
    ['prefix', '/blog/'],
    ['tag', 'blog-posts'],
]);

it('refuses more than one target', function () {
    $sent = setupPurgeEdgeCacheMocks();

    $this->artisan('environment:purge-edge-cache', [
        'environment' => 'env-1',
        '--path' => '/blog/hello-world',
        '--tag' => 'blog-posts',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent()->url ?? null)->toBeNull();
});

it('fails when the API rejects the purge', function () {
    setupPurgeEdgeCacheMocks(MockResponse::make([
        'message' => 'The environment is stopped.',
        'errors' => ['global' => ['The environment is stopped.']],
    ], 422));

    $this->artisan('environment:purge-edge-cache', ['environment' => 'env-1', '--no-interaction' => true])
        ->assertFailed();
});

it('outputs the environment as JSON', function () {
    setupPurgeEdgeCacheMocks();

    Artisan::call('environment:purge-edge-cache', ['environment' => 'env-1', '--json' => true]);

    expect(json_decode(Artisan::output(), true))->toMatchArray(['id' => 'env-1', 'name' => 'production']);
});
