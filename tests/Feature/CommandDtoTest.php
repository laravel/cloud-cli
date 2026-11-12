<?php

use App\Client\Connector;
use App\Client\Resources\Commands\GetCommandRequest;
use App\Client\Resources\Commands\ListCommandsRequest;
use App\Client\Resources\Commands\RunCommandRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use App\Dto\Command;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\Fixtures\InteractiveCommandGetCommand;
use Tests\Fixtures\InteractiveCommandRunCommand;

beforeEach(function () {
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function commandData(string $id, string $userId, ?string $deploymentId, array $attributes = []): array
{
    return [
        'id' => $id,
        'type' => 'commands',
        'attributes' => array_merge([
            'command' => 'php artisan migrate',
            'output' => null,
            'status' => 'command.success',
            'exit_code' => 0,
            'failure_reason' => null,
            'started_at' => null,
            'finished_at' => null,
            'created_at' => null,
        ], $attributes),
        'relationships' => [
            'environment' => ['data' => ['type' => 'environments', 'id' => 'env-1']],
            'deployment' => ['data' => $deploymentId ? ['type' => 'deployments', 'id' => $deploymentId] : null],
            'initiator' => ['data' => ['type' => 'users', 'id' => $userId]],
        ],
    ];
}

function commandIncluded(): array
{
    return [
        createEnvironmentResponse(),
        ['id' => 'user-1', 'type' => 'users', 'attributes' => ['name' => 'Taylor', 'email' => 'taylor@example.com']],
        ['id' => 'user-2', 'type' => 'users', 'attributes' => ['name' => 'Joe', 'email' => 'joe@example.com']],
        ['id' => 'depl-1', 'type' => 'deployments', 'attributes' => ['status' => 'deployment.succeeded']],
        ['id' => 'depl-2', 'type' => 'deployments', 'attributes' => ['status' => 'deployment.failed']],
    ];
}

it('matches each command in a list to its own included resources', function () {
    $connector = new Connector('test-api-token');
    $connector->withMockClient(new MockClient([
        ListCommandsRequest::class => MockResponse::make([
            'data' => [
                commandData('comm-1', 'user-2', 'depl-2'),
                commandData('comm-2', 'user-1', null),
            ],
            'included' => commandIncluded(),
            'links' => [],
            'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 2],
        ], 200),
    ]));

    $commands = $connector->send(new ListCommandsRequest('env-1'))->dto();

    expect($commands[0]->initiator->name)->toBe('Joe');
    expect($commands[0]->deployment->id)->toBe('depl-2');
    expect($commands[0]->environment->id)->toBe('env-1');
    expect($commands[1]->initiator->name)->toBe('Taylor');
    expect($commands[1]->deployment)->toBeNull();
});

it('leaves relationships empty when they are not included', function () {
    $command = Command::createFromResponse([
        'data' => commandData('comm-1', 'user-1', 'depl-1'),
    ]);

    expect($command->environment)->toBeNull();
    expect($command->deployment)->toBeNull();
    expect($command->initiator)->toBeNull();
});

it('includes the failure reason in the command JSON', function () {
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetCommandRequest::class => MockResponse::make([
            'data' => commandData('comm-1', 'user-1', null, [
                'status' => 'command.failure',
                'exit_code' => 1,
                'failure_reason' => 'The command timed out.',
            ]),
            'included' => commandIncluded(),
        ], 200),
    ]);

    Artisan::call('command:get', [
        'commandId' => 'comm-1',
        '--json' => true,
    ]);

    $json = json_decode(Artisan::output(), true);

    expect($json['failureReason'])->toBe('The command timed out.');
    expect($json['initiator']['name'])->toBe('Taylor');
    expect($json)->not->toHaveKey('instance');
    expect($json)->not->toHaveKey('updatedAt');
});

function truncatedCommandResponse(): array
{
    return [
        'data' => commandData('comm-1', 'user-1', null, [
            'output' => 'last lines of output',
            'output_truncated' => true,
            'output_url' => 'https://logs.example.com/command-output.json?signature=abc',
        ]),
        'included' => commandIncluded(),
    ];
}

it('includes the output truncation fields in the command JSON', function () {
    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetCommandRequest::class => MockResponse::make(truncatedCommandResponse(), 200),
    ]);

    Artisan::call('command:get', [
        'commandId' => 'comm-1',
        '--json' => true,
    ]);

    $json = json_decode(Artisan::output(), true);

    expect($json['outputTruncated'])->toBeTrue();
    expect($json['outputUrl'])->toBe('https://logs.example.com/command-output.json?signature=abc');
});

it('defaults the truncation fields when the API leaves them out', function () {
    $command = Command::createFromResponse(['data' => commandData('comm-1', 'user-1', null)]);

    expect($command->outputTruncated)->toBeFalse();
    expect($command->outputUrl)->toBeNull();
});

it('warns with the download link when command:get output is truncated', function () {
    Artisan::registerCommand(new InteractiveCommandGetCommand);

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetCommandRequest::class => MockResponse::make(truncatedCommandResponse(), 200),
    ]);

    Artisan::call('test:interactive-command-get', ['commandId' => 'comm-1']);

    expect(Artisan::output())
        ->toContain('Output is truncated')
        ->toContain('https://logs.example.com/command-output.json?signature=abc');
});

it('does not warn when command:get output is complete', function () {
    Artisan::registerCommand(new InteractiveCommandGetCommand);

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetCommandRequest::class => MockResponse::make([
            'data' => commandData('comm-1', 'user-1', null, ['output' => 'all of it']),
            'included' => commandIncluded(),
        ], 200),
    ]);

    Artisan::call('test:interactive-command-get', ['commandId' => 'comm-1']);

    expect(Artisan::output())
        ->toContain('all of it')
        ->not->toContain('truncated');
});

it('warns with the download link when command:run output is truncated', function () {
    Artisan::registerCommand(new InteractiveCommandRunCommand);

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetEnvironmentRequest::class => MockResponse::make(['data' => createEnvironmentResponse()], 200),
        RunCommandRequest::class => MockResponse::make([
            'data' => commandData('comm-1', 'user-1', null, ['status' => 'pending']),
            'included' => commandIncluded(),
        ], 200),
        GetCommandRequest::class => MockResponse::make(truncatedCommandResponse(), 200),
    ]);

    Artisan::call('test:interactive-command-run', [
        'environment' => 'env-1',
        '--cmd' => 'php artisan migrate',
    ]);

    expect(Artisan::output())
        ->toContain('Output is truncated')
        ->toContain('https://logs.example.com/command-output.json?signature=abc');
});
