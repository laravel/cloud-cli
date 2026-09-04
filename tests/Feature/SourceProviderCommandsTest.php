<?php

use App\Client\Resources\Applications\CreateApplicationRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Meta\ListRegionsRequest;
use App\ConfigRepository;
use App\Git;
use Illuminate\Testing\PendingCommand;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('isRepo')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('getRoot')->andReturn('/tmp/test-repo')->byDefault();
    $this->mockGit->shouldReceive('hasRemote')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('group/my-app')->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListRegionsRequest::class => MockResponse::make(regionsResponse(), 200),
        CreateApplicationRequest::class => MockResponse::make([
            'data' => createApplicationResponse(),
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
            ],
        ], 200),
    ]);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function createApplicationWith(array $options = []): PendingCommand
{
    return test()->artisan('application:create', array_merge([
        '--name' => 'My App',
        '--repository' => 'group/my-app',
        '--region' => 'us-east-1',
        '--no-interaction' => true,
    ], $options));
}

function sentSourceProvider(): ?string
{
    $sent = null;

    MockClient::global()->assertSent(function ($request) use (&$sent) {
        if ($request instanceof CreateApplicationRequest) {
            $sent = $request->body()->all()['source_control_provider_type'] ?? null;
        }

        return true;
    });

    return $sent;
}

it('sends the provider the origin remote points at', function (string $host, string $expected) {
    $this->mockGit->shouldReceive('remoteHost')->andReturn($host);

    createApplicationWith()->assertSuccessful();

    expect(sentSourceProvider())->toBe($expected);
})->with([
    'GitHub' => ['github.com', 'github'],
    'GitLab' => ['gitlab.com', 'gitlab'],
    'Bitbucket' => ['bitbucket.org', 'bitbucket'],
]);

it('falls back to GitHub when the remote host is not one it knows', function () {
    $this->mockGit->shouldReceive('remoteHost')->andReturn('git.example.com');

    createApplicationWith()->assertSuccessful();

    expect(sentSourceProvider())->toBe('github');
});

it('prefers an explicit --source-provider over the remote', function () {
    $this->mockGit->shouldReceive('remoteHost')->andReturn('github.com');

    createApplicationWith(['--source-provider' => 'gitlab_self_hosted'])->assertSuccessful();

    expect(sentSourceProvider())->toBe('gitlab_self_hosted');
});

it('rejects a provider the API does not accept', function () {
    $this->mockGit->shouldReceive('remoteHost')->andReturn('github.com');

    createApplicationWith(['--source-provider' => 'not-a-provider'])->assertFailed();
});
