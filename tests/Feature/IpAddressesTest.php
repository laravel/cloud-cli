<?php

use App\Client\Resources\Meta\ListIpAddressesRequest;
use App\ConfigRepository;
use App\Git;
use Illuminate\Support\Sleep;
use Laravel\Prompts\Prompt;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    Sleep::fake();
    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('isRepo')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('getRoot')->andReturn('/tmp/test-repo')->byDefault();
    $this->mockGit->shouldReceive('currentBranch')->andReturn('main')->byDefault();
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('')->byDefault();
    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(false)->byDefault();
    $this->app->instance(Git::class, $this->mockGit);
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(fn () => MockClient::destroyGlobal());

function ipAddressesResponse(): array
{
    return [
        'us-east-1' => [
            'ipv4' => ['1.2.3.4', '5.6.7.8'],
            'ipv6' => ['2001:db8::1', '2001:db8::2'],
        ],
        'eu-west-1' => [
            'ipv4' => ['10.0.0.1'],
            'ipv6' => ['2001:db8::3'],
        ],
    ];
}

it('lists IP addresses', function () {
    Prompt::fake();

    MockClient::global([
        ListIpAddressesRequest::class => MockResponse::make(ipAddressesResponse(), 200),
    ]);

    $this->artisan('ip:addresses')
        ->assertSuccessful();
});

it('outputs IP addresses as JSON with --json flag', function () {
    Prompt::fake();

    MockClient::global([
        ListIpAddressesRequest::class => MockResponse::make(ipAddressesResponse(), 200),
    ]);

    $this->artisan('ip:addresses', ['--json' => true])
        ->assertSuccessful();
});

it('filters IP addresses by region', function () {
    Prompt::fake();

    MockClient::global([
        ListIpAddressesRequest::class => MockResponse::make(ipAddressesResponse(), 200),
    ]);

    $this->artisan('ip:addresses', ['--region' => 'us-east'])
        ->assertSuccessful();
});

it('returns failure when no IP addresses match region filter', function () {
    Prompt::fake();

    MockClient::global([
        ListIpAddressesRequest::class => MockResponse::make(ipAddressesResponse(), 200),
    ]);

    $this->artisan('ip:addresses', ['--region' => 'ap-southeast'])
        ->assertFailed();
});
