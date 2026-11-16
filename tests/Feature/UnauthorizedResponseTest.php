<?php

use App\Client\Connector;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Usage\GetUsageRequest;
use App\Commands\Usage;
use App\ConfigRepository;
use Saloon\Exceptions\Request\Statuses\UnauthorizedException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    config()->set('cloud.api_token', null);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['revoked-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function unauthorizedException(string $token = 'revoked-token', array|string $body = ['message' => 'Invalid API token']): UnauthorizedException
{
    $connector = new Connector($token);
    $connector->withMockClient(new MockClient([
        GetOrganizationRequest::class => MockResponse::make($body, 401),
    ]));

    try {
        $connector->send(new GetOrganizationRequest);
    } catch (UnauthorizedException $e) {
        return $e;
    }

    throw new RuntimeException('Expected the request to be rejected.');
}

function unauthorizedMessage(UnauthorizedException $e): string
{
    $method = new ReflectionMethod(Usage::class, 'unauthorizedMessage');

    return $method->invoke(app(Usage::class), $e);
}

it('fails cleanly instead of throwing when the API rejects the token', function () {
    MockClient::global([
        GetUsageRequest::class => MockResponse::make(['message' => 'Invalid API token'], 401),
    ]);

    $this->artisan('usage', ['--no-interaction' => true])->assertFailed();
});

it('fails cleanly when the 401 body is not JSON', function () {
    MockClient::global([
        GetUsageRequest::class => MockResponse::make('<!DOCTYPE html><html><body>Unauthorized</body></html>', 401),
    ]);

    $this->artisan('usage', ['--no-interaction' => true])->assertFailed();
});

it('falls back to a generic reason when the 401 body has no usable message', function (array|string $body) {
    expect(unauthorizedMessage(unauthorizedException(body: $body)))
        ->toContain('(Unauthorized)')
        ->toContain('cloud auth');
})->with([
    'html' => '<!DOCTYPE html><html><body>Unauthorized</body></html>',
    'empty' => '',
    'non-string message' => [['message' => ['nested' => 'value']]],
]);

it('tells the user to sign in again when a stored token is rejected', function () {
    expect(unauthorizedMessage(unauthorizedException()))
        ->toContain('Invalid API token')
        ->toContain('cloud auth');
});

it('points at the environment variable when that token is rejected', function () {
    config()->set('cloud.api_token', 'revoked-env-token');

    expect(unauthorizedMessage(unauthorizedException('revoked-env-token')))
        ->toContain('Invalid API token')
        ->toContain('LARAVEL_CLOUD_TOKEN');
});

it('blames the stored token, not the environment variable, when only the stored token is rejected', function () {
    config()->set('cloud.api_token', 'valid-env-token');

    expect(unauthorizedMessage(unauthorizedException('revoked-token')))
        ->toContain('cloud auth')
        ->not->toContain('LARAVEL_CLOUD_TOKEN');
});
