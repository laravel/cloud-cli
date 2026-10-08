<?php

use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\WebSocketApplications\GetWebSocketApplicationRequest;
use App\Client\Resources\WebSocketApplications\UpdateWebSocketApplicationRequest;
use App\ConfigRepository;
use App\Dto\WebsocketApplication;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);

    $this->sent = new stdClass;
});

afterEach(function () {
    MockClient::destroyGlobal();
});

/**
 * An application as Cloud returns it to a token that may not view its credentials.
 */
function websocketApplicationWithoutCredentials(): array
{
    $application = websocketApplicationResponse();

    unset($application['attributes']['key'], $application['attributes']['secret']);

    return $application;
}

function setupWebsocketApplicationMocks(array $application): void
{
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetWebSocketApplicationRequest::class => MockResponse::make(['data' => $application], 200),
        UpdateWebSocketApplicationRequest::class => function (PendingRequest $request) use ($application) {
            test()->sent->body = $request->body()->all();

            return MockResponse::make(['data' => $application], 200);
        },
    ]);
}

it('reads an application whose credentials are hidden', function () {
    $application = WebsocketApplication::createFromResponse(['data' => websocketApplicationWithoutCredentials()]);

    expect($application->key)->toBeNull()
        ->and($application->secret)->toBeNull();
});

it('gets an application whose credentials are hidden', function () {
    setupWebsocketApplicationMocks(websocketApplicationWithoutCredentials());

    Artisan::call('websocket-application:get', ['application' => 'wsa-1', '--json' => true]);

    expect(json_decode(Artisan::output(), true))->toMatchArray(['id' => 'wsa-1', 'key' => null, 'secret' => null]);
});

it('sends the fields it is given and leaves out the name', function () {
    setupWebsocketApplicationMocks(websocketApplicationResponse());

    $this->artisan('websocket-application:update', [
        'application' => 'wsa-1',
        '--allowed-origins' => 'https://example.com, https://app.example.com',
        '--ping-interval' => '30',
        '--activity-timeout' => '20',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->sent->body)->toBe([
        'allowed_origins' => ['https://example.com', 'https://app.example.com'],
        'ping_interval' => 30,
        'activity_timeout' => 20,
    ]);
});

it('still renames an application', function () {
    setupWebsocketApplicationMocks(websocketApplicationResponse());

    $this->artisan('websocket-application:update', [
        'application' => 'wsa-1',
        '--name' => 'renamed',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->sent->body)->toBe(['name' => 'renamed']);
});
