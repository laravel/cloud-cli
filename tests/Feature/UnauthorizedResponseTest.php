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

function unauthorizedException(): UnauthorizedException
{
    $connector = new Connector('revoked-token');
    $connector->withMockClient(new MockClient([
        GetOrganizationRequest::class => MockResponse::make(['message' => 'Invalid API token'], 401),
    ]));

    try {
        $connector->send(new GetOrganizationRequest);
    } catch (UnauthorizedException $e) {
        return $e;
    }

    throw new RuntimeException('Expected the request to be rejected.');
}

function unauthorizedMessage(): string
{
    $method = new ReflectionMethod(Usage::class, 'unauthorizedMessage');

    return $method->invoke(app(Usage::class), unauthorizedException());
}

it('fails cleanly instead of throwing when the API rejects the token', function () {
    MockClient::global([
        GetUsageRequest::class => MockResponse::make(['message' => 'Invalid API token'], 401),
    ]);

    $this->artisan('usage', ['--no-interaction' => true])->assertFailed();
});

it('tells the user to sign in again when a stored token is rejected', function () {
    expect(unauthorizedMessage())
        ->toContain('Invalid API token')
        ->toContain('cloud auth');
});

it('points at the environment variable when that token is rejected', function () {
    config()->set('cloud.api_token', 'revoked-env-token');

    expect(unauthorizedMessage())
        ->toContain('Invalid API token')
        ->toContain('LARAVEL_CLOUD_TOKEN');
});
