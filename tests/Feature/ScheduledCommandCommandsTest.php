<?php

use App\Client\Resources\Instances\GetInstanceRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\ScheduledCommands\CreateScheduledCommandRequest;
use App\Client\Resources\ScheduledCommands\DeleteScheduledCommandRequest;
use App\Client\Resources\ScheduledCommands\GetScheduledCommandRequest;
use App\Client\Resources\ScheduledCommands\ListScheduledCommandsRequest;
use App\Client\Resources\ScheduledCommands\UpdateScheduledCommandRequest;
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

function scheduledCommandResponse(array $attributes = []): array
{
    return [
        'id' => 'schcmd-1',
        'type' => 'scheduled_commands',
        'attributes' => array_merge([
            'name' => 'Prune models',
            'command' => 'php artisan model:prune',
            'schedule' => '0 0 * * *',
            'created_at' => '2026-01-01T00:00:00.000000Z',
        ], $attributes),
        'relationships' => ['instance' => ['data' => ['id' => 'inst-1', 'type' => 'instances']]],
    ];
}

function scheduledCommandInstanceResponse(): array
{
    return ['data' => [
        'id' => 'inst-1',
        'type' => 'instances',
        'attributes' => [
            'name' => 'web',
            'type' => 'app',
            'size' => 'flex.c-1vcpu-256mb',
            'scaling_type' => 'none',
            'min_replicas' => 1,
            'max_replicas' => 1,
            'uses_scheduler' => true,
        ],
    ]];
}

function setupScheduledCommandMocks(): stdClass
{
    $sent = new stdClass;
    $sent->body = null;
    $sent->deleted = false;

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetInstanceRequest::class => MockResponse::make(scheduledCommandInstanceResponse(), 200),
        ListScheduledCommandsRequest::class => MockResponse::make([
            'data' => [
                scheduledCommandResponse(),
                scheduledCommandResponse(['name' => null, 'command' => 'php artisan backup:run', 'schedule' => '@hourly']),
            ],
            'links' => ['next' => null],
        ], 200),
        GetScheduledCommandRequest::class => MockResponse::make(['data' => scheduledCommandResponse()], 200),
        CreateScheduledCommandRequest::class => function (PendingRequest $request) use ($sent) {
            $sent->body = $request->body()->all();
            $sent->url = $request->getUrl();

            return MockResponse::make(['data' => scheduledCommandResponse($sent->body)], 201);
        },
        UpdateScheduledCommandRequest::class => function (PendingRequest $request) use ($sent) {
            $sent->body = $request->body()->all();
            $sent->url = $request->getUrl();

            return MockResponse::make(['data' => scheduledCommandResponse($sent->body)], 200);
        },
        DeleteScheduledCommandRequest::class => function (PendingRequest $request) use ($sent) {
            $sent->deleted = true;
            $sent->url = $request->getUrl();

            return MockResponse::make([], 204);
        },
    ]);

    return $sent;
}

it('lists scheduled commands for an instance as json', function () {
    setupScheduledCommandMocks();

    Artisan::call('scheduled-command:list', ['instance' => 'inst-1', '--json' => true, '--no-interaction' => true]);

    expect(collect(json_decode(Artisan::output(), true))->pluck('schedule')->all())->toBe(['0 0 * * *', '@hourly']);
});

it('gets a scheduled command with its instance', function () {
    setupScheduledCommandMocks();

    Artisan::call('scheduled-command:get', ['scheduled-command' => 'schcmd-1', '--json' => true, '--no-interaction' => true]);

    expect(json_decode(Artisan::output(), true))->toMatchArray([
        'id' => 'schcmd-1',
        'name' => 'Prune models',
        'command' => 'php artisan model:prune',
        'schedule' => '0 0 * * *',
        'instanceId' => 'inst-1',
    ]);
});

it('creates a scheduled command without a name', function () {
    $sent = setupScheduledCommandMocks();

    $this->artisan('scheduled-command:create', [
        'instance' => 'inst-1',
        '--command' => 'php artisan backup:run',
        '--schedule' => '*/5 * * * *',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->url)->toEndWith('/instances/inst-1/scheduled-commands');
    expect($sent->body)->toBe(['command' => 'php artisan backup:run', 'schedule' => '*/5 * * * *']);
});

it('requires a schedule when creating non-interactively', function () {
    $sent = setupScheduledCommandMocks();

    $this->artisan('scheduled-command:create', [
        'instance' => 'inst-1',
        '--command' => 'php artisan backup:run',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent->body)->toBeNull();
});

it('sends only the changed fields on update', function () {
    $sent = setupScheduledCommandMocks();

    $this->artisan('scheduled-command:update', [
        'scheduled-command' => 'schcmd-1',
        '--schedule' => '@daily',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->url)->toEndWith('/scheduled-commands/schcmd-1');
    expect($sent->body)->toBe(['schedule' => '@daily']);
});

it('requires --force to delete non-interactively', function () {
    $sent = setupScheduledCommandMocks();

    $this->artisan('scheduled-command:delete', ['scheduled-command' => 'schcmd-1', '--no-interaction' => true])
        ->assertFailed();

    expect($sent->deleted)->toBeFalse();

    $this->artisan('scheduled-command:delete', ['scheduled-command' => 'schcmd-1', '--force' => true, '--no-interaction' => true])
        ->assertSuccessful();

    expect($sent->deleted)->toBeTrue();
    expect($sent->url)->toEndWith('/scheduled-commands/schcmd-1');
});
