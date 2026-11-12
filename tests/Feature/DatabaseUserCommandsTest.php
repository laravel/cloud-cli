<?php

use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseUsers\CreateDatabaseUserRequest;
use App\Client\Resources\DatabaseUsers\DeleteDatabaseUserRequest;
use App\Client\Resources\DatabaseUsers\GetDatabaseUserRequest;
use App\Client\Resources\DatabaseUsers\ListDatabaseUsersRequest;
use App\Client\Resources\DatabaseUsers\UpdateDatabaseUserRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use App\Dto\DatabaseUser;
use App\Enums\DatabaseUserStatus;
use App\Enums\DatabaseUserType;
use App\Prompts\Renderer;
use Illuminate\Support\Facades\Artisan;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Tests\Fixtures\InteractiveDatabaseUserCreateCommand;
use Tests\Fixtures\InteractiveDatabaseUserUpdateCommand;

beforeEach(function () {
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function databaseUserSchemas(): array
{
    return [
        databaseSchemaResponse(),
        databaseSchemaResponse(['id' => 'schema-2', 'attributes' => ['name' => 'analytics']]),
    ];
}

function databaseUserListResponse(?array $users = null): array
{
    return [
        'data' => $users ?? [
            databaseUserResponse(['id' => 'dbu-super', 'attributes' => ['username' => 'laravel', 'type' => 'superuser'], 'databases' => ['schema-1', 'schema-2']]),
            databaseUserResponse(),
        ],
        'included' => databaseUserSchemas(),
        'links' => ['next' => null],
    ];
}

/**
 * @return Closure(): ?array The body of the last create or update request, once one is sent.
 */
function setupDatabaseUserMocks(array $created = []): Closure
{
    $sentBody = null;

    $user = databaseUserResponse();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetDatabaseClusterRequest::class => MockResponse::make(databaseClusterResponse(['included' => databaseUserSchemas()]), 200),
        ListDatabaseUsersRequest::class => MockResponse::make(databaseUserListResponse(), 200),
        GetDatabaseUserRequest::class => MockResponse::make(['data' => $user, 'included' => databaseUserSchemas()], 200),
        CreateDatabaseUserRequest::class => function (PendingRequest $request) use (&$sentBody, $created) {
            $sentBody = $request->body()->all();

            return MockResponse::make([
                'data' => databaseUserResponse(array_replace_recursive([
                    'attributes' => [
                        'username' => $sentBody['username'],
                        'type' => $sentBody['type'],
                        'status' => 'creating',
                        'password' => 'one-time-password',
                    ],
                    'databases' => $sentBody['databases'],
                ], $created)),
                'included' => databaseUserSchemas(),
            ], 201);
        },
        UpdateDatabaseUserRequest::class => function (PendingRequest $request) use (&$sentBody) {
            $sentBody = $request->body()->all();

            return MockResponse::make([
                'data' => databaseUserResponse([
                    'attributes' => ['type' => $sentBody['type']],
                    'databases' => $sentBody['databases'],
                ]),
                'included' => databaseUserSchemas(),
            ], 200);
        },
        DeleteDatabaseUserRequest::class => MockResponse::make([], 204),
    ]);

    return function () use (&$sentBody) {
        return $sentBody;
    };
}

it('maps a user and only its own databases from a shared included list', function () {
    $user = DatabaseUser::createFromResponse([
        'data' => databaseUserResponse(['databases' => ['schema-2']]),
        'included' => databaseUserSchemas(),
    ]);

    expect($user->id)->toBe('dbu-1')
        ->and($user->username)->toBe('app_reader')
        ->and($user->type)->toBe(DatabaseUserType::READ_ONLY)
        ->and($user->status)->toBe(DatabaseUserStatus::AVAILABLE)
        ->and($user->password)->toBeNull()
        ->and(collect($user->databases)->pluck('name')->all())->toBe(['analytics']);
});

it('lists users under the cluster with their databases', function () {
    setupDatabaseUserMocks();

    Artisan::call('database-user:list', ['cluster' => 'db-123', '--json' => true]);

    $users = json_decode(Artisan::output(), true);

    expect($users)->toHaveCount(2)
        ->and($users[0])->toMatchArray(['id' => 'dbu-super', 'username' => 'laravel', 'type' => 'superuser'])
        ->and(collect($users[0]['databases'])->pluck('name')->all())->toBe(['my_schema', 'analytics'])
        ->and(collect($users[1]['databases'])->pluck('name')->all())->toBe(['my_schema']);

    MockClient::global()->assertSent(function ($request) {
        return $request instanceof ListDatabaseUsersRequest
            && $request->resolveEndpoint() === '/databases/clusters/db-123/users'
            && $request->query()->get('include') === 'databases';
    });
});

it('gets a user by username', function () {
    setupDatabaseUserMocks();

    $this->artisan('database-user:get', [
        'cluster' => 'db-123',
        'user' => 'app_reader',
        '--no-interaction' => true,
    ])->assertSuccessful();

    MockClient::global()->assertSent(function ($request) {
        return $request instanceof GetDatabaseUserRequest
            && $request->resolveEndpoint() === '/databases/clusters/db-123/users/dbu-1';
    });
});

it('fails to get a user that does not exist', function () {
    setupDatabaseUserMocks();

    $this->artisan('database-user:get', [
        'cluster' => 'db-123',
        'user' => 'nobody',
        '--no-interaction' => true,
    ])->assertFailed();

    MockClient::global()->assertNotSent(GetDatabaseUserRequest::class);
});

it('creates a user headlessly, resolving databases by ID or name', function () {
    $sentBody = setupDatabaseUserMocks();

    $this->artisan('database-user:create', [
        'cluster' => 'db-123',
        '--username' => 'app_writer',
        '--type' => 'read_write',
        '--database' => ['analytics', 'schema-1'],
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody())->toBe([
        'username' => 'app_writer',
        'type' => 'read_write',
        'databases' => ['schema-2', 'schema-1'],
    ]);

    MockClient::global()->assertSent(function ($request) {
        return $request instanceof CreateDatabaseUserRequest
            && $request->resolveEndpoint() === '/databases/clusters/db-123/users'
            && $request->query()->get('include') === 'databases';
    });
});

it('creates a read-write user with no databases when only a username is given headlessly', function () {
    $sentBody = setupDatabaseUserMocks();

    $this->artisan('database-user:create', [
        'cluster' => 'db-123',
        '--username' => 'app_writer',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody())->toBe([
        'username' => 'app_writer',
        'type' => 'read_write',
        'databases' => [],
    ]);
});

it('requires a username when creating a user headlessly', function () {
    setupDatabaseUserMocks();

    $this->artisan('database-user:create', [
        'cluster' => 'db-123',
        '--no-interaction' => true,
    ])->assertFailed();

    MockClient::global()->assertNotSent(CreateDatabaseUserRequest::class);
});

it('refuses a database that is not in the cluster', function () {
    setupDatabaseUserMocks();

    $this->artisan('database-user:create', [
        'cluster' => 'db-123',
        '--username' => 'app_writer',
        '--database' => ['missing'],
        '--no-interaction' => true,
    ])->assertFailed();

    MockClient::global()->assertNotSent(CreateDatabaseUserRequest::class);
});

it('masks the one-time password in json by default and says it cannot be retrieved', function () {
    setupDatabaseUserMocks();

    $this->artisan('database-user:create', [
        'cluster' => 'db-123',
        '--username' => 'app_writer',
        '--json' => true,
        '--no-interaction' => true,
    ])
        ->assertSuccessful()
        ->doesntExpectOutputToContain('one-time-password')
        ->expectsOutputToContain('"password":"*****"');
});

it('reveals the one-time password in json with --show-sensitive', function () {
    setupDatabaseUserMocks();

    Artisan::call('database-user:create', [
        'cluster' => 'db-123',
        '--username' => 'app_writer',
        '--database' => ['my_schema'],
        '--json' => true,
        '--show-sensitive' => true,
    ]);

    $user = json_decode(Artisan::output(), true);

    expect($user)->toMatchArray([
        'username' => 'app_writer',
        'type' => 'read_write',
        'status' => 'creating',
        'password' => 'one-time-password',
    ])->and(collect($user['databases'])->pluck('id')->all())->toBe(['schema-1']);
});

it('shows the one-time password to a person creating a user interactively', function () {
    Artisan::registerCommand(new InteractiveDatabaseUserCreateCommand);

    $sentBody = setupDatabaseUserMocks();

    Prompt::fake([
        ...str_split('app_reader'),
        Key::ENTER,
        // Permission: move from read and write to read only.
        Key::DOWN,
        Key::ENTER,
        // Databases: tick the second one.
        Key::DOWN,
        Key::SPACE,
        Key::ENTER,
    ]);

    $suppressed = Renderer::$suppressOutput;

    try {
        $this->artisan('test:interactive-database-user-create', ['cluster' => 'db-123'])
            ->assertSuccessful()
            ->expectsOutputToContain('one-time-password')
            ->expectsOutputToContain('It will not be shown again.');
    } finally {
        Renderer::$suppressOutput = $suppressed;
    }

    expect($sentBody())->toBe([
        'username' => 'app_reader',
        'type' => 'read_only',
        'databases' => ['schema-2'],
    ]);
});

it('updates only the permission headlessly and keeps the current databases', function () {
    $sentBody = setupDatabaseUserMocks();

    $this->artisan('database-user:update', [
        'cluster' => 'db-123',
        'user' => 'dbu-1',
        '--type' => 'read_write',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody())->toBe([
        'type' => 'read_write',
        'databases' => ['schema-1'],
    ]);

    MockClient::global()->assertSent(function ($request) {
        return $request instanceof UpdateDatabaseUserRequest
            && $request->resolveEndpoint() === '/databases/clusters/db-123/users/dbu-1';
    });
});

it('replaces the databases headlessly and keeps the current permission', function () {
    $sentBody = setupDatabaseUserMocks();

    $this->artisan('database-user:update', [
        'cluster' => 'db-123',
        'user' => 'app_reader',
        '--database' => ['analytics'],
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody())->toBe([
        'type' => 'read_only',
        'databases' => ['schema-2'],
    ]);
});

it('removes every database with --no-databases', function () {
    $sentBody = setupDatabaseUserMocks();

    $this->artisan('database-user:update', [
        'cluster' => 'db-123',
        'user' => 'app_reader',
        '--no-databases' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sentBody())->toBe([
        'type' => 'read_only',
        'databases' => [],
    ]);
});

it('refuses --database together with --no-databases', function () {
    setupDatabaseUserMocks();

    $this->artisan('database-user:update', [
        'cluster' => 'db-123',
        'user' => 'app_reader',
        '--database' => ['analytics'],
        '--no-databases' => true,
        '--no-interaction' => true,
    ])->assertFailed();

    MockClient::global()->assertNotSent(UpdateDatabaseUserRequest::class);
});

it('fails headlessly when there is nothing to update', function () {
    setupDatabaseUserMocks();

    $this->artisan('database-user:update', [
        'cluster' => 'db-123',
        'user' => 'app_reader',
        '--no-interaction' => true,
    ])->assertFailed();

    MockClient::global()->assertNotSent(UpdateDatabaseUserRequest::class);
});

it('picks what to update interactively', function () {
    Artisan::registerCommand(new InteractiveDatabaseUserUpdateCommand);

    $sentBody = setupDatabaseUserMocks();

    Prompt::fake([
        // What to update: tick Databases only.
        Key::DOWN,
        Key::SPACE,
        Key::ENTER,
        // Databases: my_schema is ticked already, add analytics.
        Key::DOWN,
        Key::SPACE,
        Key::ENTER,
    ]);

    $this->artisan('test:interactive-database-user-update', [
        'cluster' => 'db-123',
        'user' => 'app_reader',
    ])->assertSuccessful();

    expect($sentBody())->toBe([
        'type' => 'read_only',
        'databases' => ['schema-1', 'schema-2'],
    ]);
});

it('refuses to update the superuser', function () {
    setupDatabaseUserMocks();

    $this->artisan('database-user:update', [
        'cluster' => 'db-123',
        'user' => 'laravel',
        '--type' => 'read_only',
        '--no-interaction' => true,
    ])->assertFailed();

    MockClient::global()->assertNotSent(UpdateDatabaseUserRequest::class);
});

it('deletes a user with --force', function () {
    setupDatabaseUserMocks();

    $this->artisan('database-user:delete', [
        'cluster' => 'db-123',
        'user' => 'app_reader',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    MockClient::global()->assertSent(function ($request) {
        return $request instanceof DeleteDatabaseUserRequest
            && $request->resolveEndpoint() === '/databases/clusters/db-123/users/dbu-1';
    });
});

it('requires --force to delete a user headlessly', function () {
    setupDatabaseUserMocks();

    $this->artisan('database-user:delete', [
        'cluster' => 'db-123',
        'user' => 'app_reader',
        '--no-interaction' => true,
    ])->assertFailed();

    MockClient::global()->assertNotSent(DeleteDatabaseUserRequest::class);
});

it('leaves the superuser out when picking a user to delete', function () {
    setupDatabaseUserMocks();

    $this->artisan('database-user:delete', [
        'cluster' => 'db-123',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    MockClient::global()->assertSent(function ($request) {
        return $request instanceof DeleteDatabaseUserRequest
            && $request->resolveEndpoint() === '/databases/clusters/db-123/users/dbu-1';
    });
});

it('refuses to delete the superuser', function () {
    setupDatabaseUserMocks();

    $this->artisan('database-user:delete', [
        'cluster' => 'db-123',
        'user' => 'laravel',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();

    MockClient::global()->assertNotSent(DeleteDatabaseUserRequest::class);
});
