<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Deployments\GetDeploymentRequest;
use App\Client\Resources\Deployments\InitiateDeploymentRequest;
use App\Client\Resources\Environments\AddEnvironmentVariablesRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
use App\ConfigRepository;
use App\Git;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Sleep;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Tests\Fixtures\InteractiveDeployCommand;

beforeEach(function () {
    Sleep::fake();

    $git = Mockery::mock(Git::class);
    $git->shouldReceive('isRepo')->andReturn(true);
    $git->shouldReceive('getRoot')->andReturn('/tmp/test-repo');
    $git->shouldReceive('currentBranch')->andReturn('main');
    $git->shouldReceive('hasRemote')->andReturn(true);
    $git->shouldReceive('remoteRepo')->andReturn('user/my-app');
    $this->app->instance(Git::class, $git);

    $config = Mockery::mock(ConfigRepository::class);
    $config->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $config);

    $this->originalCwd = getcwd();
    $this->projectDir = sys_get_temp_dir().'/deploy-push-env-'.uniqid();
    mkdir($this->projectDir);
    file_put_contents($this->projectDir.'/.env', implode("\n", [
        'APP_NAME=Local',
        'NEW_KEY=new',
        'SAME_KEY=same',
        'CHANGED_KEY=local',
    ]));
    chdir($this->projectDir);

    $this->sentVariables = new stdClass;

    $environment = createEnvironmentResponse(['attributes' => ['environment_variables' => [
        ['key' => 'SAME_KEY', 'value' => 'same'],
        ['key' => 'CHANGED_KEY', 'value' => 'remote'],
    ]]]);

    MockClient::global([
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [createApplicationResponse()],
            'included' => [$environment],
            'links' => ['next' => null],
        ], 200),
        ListEnvironmentsRequest::class => MockResponse::make(['data' => [$environment], 'links' => ['next' => null]], 200),
        GetEnvironmentRequest::class => MockResponse::make(['data' => $environment], 200),
        AddEnvironmentVariablesRequest::class => function (PendingRequest $request) use ($environment) {
            $this->sentVariables->value = $request->body()->all();

            return MockResponse::make(['data' => $environment], 200);
        },
        InitiateDeploymentRequest::class => MockResponse::make(['data' => ['id' => 'deploy-123', 'type' => 'deployments', 'attributes' => ['status' => 'pending']]], 200),
        GetDeploymentRequest::class => MockResponse::make(['data' => ['id' => 'deploy-123', 'type' => 'deployments', 'attributes' => [
            'status' => 'deployment.succeeded',
            'started_at' => now()->toISOString(),
            'finished_at' => now()->toISOString(),
        ]]], 200),
    ]);
});

afterEach(function () {
    chdir($this->originalCwd);
    unlink($this->projectDir.'/.env');
    rmdir($this->projectDir);
    MockClient::destroyGlobal();
});

it('offers only new or changed custom keys and sets the chosen ones before deploying', function () {
    Artisan::registerCommand(new InteractiveDeployCommand);

    // Pick both offered keys. If APP_NAME or SAME_KEY were offered, a different pair would be picked.
    Prompt::fake([Key::SPACE, Key::DOWN, Key::SPACE, Key::ENTER]);

    $this->artisan('test:interactive-deploy', ['--push-env' => null])->assertSuccessful();

    expect($this->sentVariables->value)->toBe([
        'method' => 'set',
        'variables' => [
            ['key' => 'NEW_KEY', 'value' => 'new'],
            ['key' => 'CHANGED_KEY', 'value' => 'local'],
        ],
    ]);

    MockClient::global()->assertSent(InitiateDeploymentRequest::class);
});

it('sets the named keys without prompting', function () {
    $this->artisan('deploy', ['--push-env' => 'SAME_KEY, APP_NAME', '--no-interaction' => true])->assertSuccessful();

    expect($this->sentVariables->value)->toBe([
        'method' => 'set',
        'variables' => [
            ['key' => 'SAME_KEY', 'value' => 'same'],
            ['key' => 'APP_NAME', 'value' => 'Local'],
        ],
    ]);

    MockClient::global()->assertSent(InitiateDeploymentRequest::class);
});

it('does not deploy when a named key is missing from the local .env', function () {
    $this->artisan('deploy', ['--push-env' => 'NEW_KEY,MISSING_KEY', '--no-interaction' => true])->assertFailed();

    MockClient::global()->assertNotSent(AddEnvironmentVariablesRequest::class);
    MockClient::global()->assertNotSent(InitiateDeploymentRequest::class);
});

it('requires named keys when not interactive', function () {
    $this->artisan('deploy', ['--push-env' => null, '--no-interaction' => true])->assertFailed();

    MockClient::global()->assertNotSent(InitiateDeploymentRequest::class);
});

it('leaves variables alone without the option', function () {
    $this->artisan('deploy', ['--no-interaction' => true])->assertSuccessful();

    MockClient::global()->assertNotSent(AddEnvironmentVariablesRequest::class);
});
