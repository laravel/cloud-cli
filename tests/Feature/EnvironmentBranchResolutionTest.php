<?php

use App\Client\Connector;
use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use App\Dto\Application;
use App\Git;
use App\LocalConfig;
use App\Prompts\Renderer;
use App\Resolvers\EnvironmentResolver;
use Illuminate\Support\Str;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    // Commands run earlier in the suite leave output suppressed, which blanks every prompt.
    Renderer::$suppressOutput = false;

    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('')->byDefault();
    $this->mockGit->shouldReceive('currentBranch')->andReturn('main')->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);

    $this->localConfig = Mockery::mock(LocalConfig::class);
    $this->localConfig->shouldReceive('get')->with('organization_id')->andReturn(null)->byDefault();
    $this->localConfig->shouldReceive('applicationId')->andReturn(null)->byDefault();
    $this->localConfig->shouldReceive('environmentId')->andReturn(null)->byDefault();
    $this->localConfig->shouldReceive('path')->andReturn('/project/.cloud/config.json')->byDefault();
    $this->app->instance(LocalConfig::class, $this->localConfig);

    // Two environments deploy main; a third deploys develop.
    $environment = fn (string $id, string $name, string $branchId) => createEnvironmentResponse([
        'id' => $id,
        'attributes' => ['name' => $name],
        'relationships' => ['branch' => ['data' => ['id' => $branchId, 'type' => 'branches']]],
    ]);

    $this->environments = [
        $environment('env-1', 'production', 'branch-main'),
        $environment('env-2', 'staging', 'branch-main'),
        $environment('env-3', 'dev', 'branch-develop'),
    ];

    $this->listQuery = null;

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [createApplicationResponse()],
            'included' => [organizationResponse()['data'], ...$this->environments],
            'links' => ['next' => null],
        ], 200),
        ListEnvironmentsRequest::class => function (PendingRequest $request) {
            $this->listQuery = $request->query()->all();

            return MockResponse::make([
                'data' => $this->environments,
                'included' => [
                    ['id' => 'branch-main', 'type' => 'branches', 'attributes' => ['name' => 'main']],
                    ['id' => 'branch-develop', 'type' => 'branches', 'attributes' => ['name' => 'develop']],
                ],
                'links' => ['next' => null],
            ], 200);
        },
        GetEnvironmentRequest::class => fn (PendingRequest $request) => MockResponse::make([
            'data' => collect($this->environments)->firstWhere('id', Str::afterLast($request->getUrl(), '/')),
        ], 200),
    ]);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function branchResolver(bool $interactive): EnvironmentResolver
{
    $application = Application::createFromResponse([
        'data' => createApplicationResponse(),
        'included' => test()->environments,
    ]);

    return (new EnvironmentResolver(new Connector('test-api-token'), test()->localConfig, $interactive))
        ->withApplication($application);
}

it('asks for the branch when listing environments', function () {
    $this->mockGit->shouldReceive('currentBranch')->andReturn('develop');

    branchResolver(interactive: false)->fromBranch();

    expect($this->listQuery['include'] ?? '')->toContain('branch');
});

it('picks the only environment that deploys the current branch', function () {
    $this->mockGit->shouldReceive('currentBranch')->andReturn('develop');

    expect(branchResolver(interactive: false)->fromBranch()->id)->toBe('env-3');
});

it('asks which environment to use when several deploy the current branch', function () {
    Prompt::fake([Key::DOWN, Key::ENTER]);

    expect(branchResolver(interactive: true)->fromBranch()->id)->toBe('env-2');

    Prompt::assertOutputContains('production');
    Prompt::assertOutputContains('staging');
    Prompt::assertOutputDoesntContain('dev ');
});

it('fails non-interactively when several environments deploy the current branch', function () {
    $this->artisan('environment:get', ['--no-interaction' => true])->assertFailed();

    MockClient::global()->assertNotSent(GetEnvironmentRequest::class);
});
