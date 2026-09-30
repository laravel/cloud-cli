<?php

use App\Client\Connector;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function secretsPage(int $page, int $size, bool $hasNext = true): MockResponse
{
    return MockResponse::make([
        'data' => collect(range(1, $size))
            ->map(fn (int $i) => secretResponse(['id' => "secret-{$page}-{$i}"]))
            ->all(),
        'links' => ['next' => $hasNext ? 'https://cloud.laravel.com/api/secrets?page='.($page + 1) : null],
    ]);
}

it('stops requesting pages once the list cap is reached', function () {
    $mockClient = new MockClient(collect(range(1, 6))->map(fn (int $page) => secretsPage($page, 500))->all());

    $connector = new Connector('test-api-token');
    $connector->withMockClient($mockClient);

    $items = $connector->secrets()->list()->collect()->collect();

    expect($items)->toHaveCount(Connector::MAX_LIST_RESULTS);
    $mockClient->assertSentCount(4);
});

it('trims a page that runs past the list cap', function () {
    $mockClient = new MockClient([secretsPage(1, 1500), secretsPage(2, 1500), secretsPage(3, 1500)]);

    $connector = new Connector('test-api-token');
    $connector->withMockClient($mockClient);

    $items = $connector->secrets()->list()->collect()->collect();

    expect($items)->toHaveCount(Connector::MAX_LIST_RESULTS);
    $mockClient->assertSentCount(2);
});

it('caps list command output', function () {
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse()),
        ...collect(range(1, 5))->map(fn (int $page) => secretsPage($page, 500))->all(),
    ]);

    Artisan::call('secret:list', ['--json' => true]);

    expect(json_decode(Artisan::output(), true))->toHaveCount(Connector::MAX_LIST_RESULTS);
    MockClient::global()->assertSentCount(5);
});
