<?php

use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Resend\AttachResendRequest;
use App\Client\Resources\Resend\DetachResendRequest;
use App\Client\Resources\Resend\ListResendDomainsRequest;
use App\Client\Resources\Resend\ListResendSendingKeysRequest;
use App\Client\Resources\Resend\UpdateResendRequest;
use App\Cloud;
use App\ConfigRepository;
use App\Dto\Application;
use App\Dto\Organization;
use App\Git;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    $this->composer = Mockery::mock(Composer::class);
    $this->composer->shouldReceive('setWorkingPath')->andReturnSelf();
    $this->composer->shouldReceive('hasPackage')->with('resend/resend-php')->andReturn(true)->byDefault();
    $this->app->instance(Composer::class, $this->composer);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function resendSettings(array $overrides = []): array
{
    return array_merge([
        'from_address' => 'hello@example.com',
        'from_name' => 'My App',
        'key_name' => 'laravel-cloud-env-1',
    ], $overrides);
}

function resendDomainResponse(array $overrides = []): array
{
    return [
        'id' => $overrides['id'] ?? 'rd-1',
        'type' => 'resend_domains',
        'attributes' => array_merge([
            'name' => 'example.com',
            'region' => 'us-east-1',
            'status' => 'verified',
            'records' => [],
            'last_verified_at' => '2026-10-01T12:00:00.000000Z',
            'created_at' => '2026-09-01T12:00:00.000000Z',
        ], $overrides['attributes'] ?? []),
    ];
}

/**
 * @return Closure(): stdClass the last request each Resend endpoint received
 */
function setupResendMocks(?array $resend = null, ?array $domains = null, array $keys = [], ?MockResponse $domainsResponse = null): Closure
{
    $sent = new stdClass;

    $record = function (string $name, MockResponse $response) use ($sent) {
        return function (PendingRequest $request) use ($sent, $name, $response) {
            $sent->{$name} = [
                'url' => $request->getUrl(),
                'query' => $request->query()->all(),
                'body' => $request->body()?->all(),
            ];

            return $response;
        };
    };

    $updated = ['data' => createEnvironmentResponse(['attributes' => ['resend' => resendSettings(['from_address' => 'team@example.com'])]])];

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetEnvironmentRequest::class => $record('environment', MockResponse::make([
            'data' => createEnvironmentResponse([
                'attributes' => [
                    'resend' => $resend,
                    'environment_variables' => [['key' => 'MAIL_MAILER', 'value' => 'log'], ['key' => 'APP_ENV', 'value' => 'production']],
                ],
                'relationships' => ['application' => ['data' => ['id' => 'app-123', 'type' => 'applications']]],
            ]),
            'included' => [createApplicationResponse()],
        ], 200)),
        ListResendDomainsRequest::class => $record('domains', $domainsResponse ?? MockResponse::make([
            'data' => $domains ?? [resendDomainResponse()],
            'links' => ['next' => null],
        ], 200)),
        ListResendSendingKeysRequest::class => $record('keys', MockResponse::make(['data' => $keys], 200)),
        AttachResendRequest::class => $record('attach', MockResponse::make($updated, 200)),
        UpdateResendRequest::class => $record('update', MockResponse::make($updated, 200)),
        DetachResendRequest::class => $record('detach', MockResponse::make('', 204)),
    ]);

    return fn () => $sent;
}

it('attaches Resend with a new sending key', function () {
    $sent = setupResendMocks();

    $this->artisan('resend:attach', [
        'environment' => 'env-1',
        '--from-address' => 'team@example.com',
        '--from-name' => 'Team',
        '--key-name' => 'my-key',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent()->attach['url'])->toEndWith('/environments/env-1/resend');
    expect($sent()->attach['body'])->toBe([
        'key_strategy' => 'create',
        'from_address' => 'team@example.com',
        'from_name' => 'Team',
        'key_name' => 'my-key',
    ]);
});

it('never installs resend/resend-php when run non-interactively', function () {
    setupResendMocks();

    $this->composer->shouldReceive('hasPackage')->with('resend/resend-php')->andReturn(false);
    $this->composer->shouldNotReceive('requirePackages');

    $this->artisan('resend:attach', [
        'environment' => 'env-1',
        '--from-address' => 'team@example.com',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('only offers verified sending domains', function () {
    $sent = setupResendMocks();

    $this->artisan('resend:attach', [
        'environment' => 'env-1',
        '--from-address' => 'team@example.com',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent()->domains['query'])->toMatchArray(['filter[status]' => 'verified']);
});

it('defaults the sender name to the application name', function () {
    $sent = setupResendMocks();

    $this->artisan('resend:attach', [
        'environment' => 'env-1',
        '--from-address' => 'team@example.com',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent()->environment['query'])->toMatchArray(['include' => 'application']);
    expect($sent()->attach['body']['from_name'])->toBe('My App');
});

it('reuses a sending key and drops the key name', function () {
    $sent = setupResendMocks();

    $this->artisan('resend:attach', [
        'environment' => 'env-1',
        '--from-address' => 'team@example.com',
        '--from-name' => 'Team',
        '--key-name' => 'ignored',
        '--reuse-key-id' => 'key-1',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent()->attach['body'])->toBe([
        'key_strategy' => 'reuse',
        'from_address' => 'team@example.com',
        'from_name' => 'Team',
        'reuse_key_id' => 'key-1',
    ]);
});

it('outputs the environment with its Resend sender as JSON', function () {
    setupResendMocks();

    Artisan::call('resend:attach', [
        'environment' => 'env-1',
        '--from-address' => 'team@example.com',
        '--json' => true,
    ]);

    expect(json_decode(Artisan::output(), true)['resend'])->toBe([
        'fromAddress' => 'team@example.com',
        'fromName' => 'My App',
        'keyName' => 'laravel-cloud-env-1',
    ]);
});

it('requires a sender address when attaching non-interactively', function () {
    $sent = setupResendMocks();

    $this->artisan('resend:attach', [
        'environment' => 'env-1',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent()->attach ?? null)->toBeNull();
});

it('refuses to attach Resend twice', function () {
    $sent = setupResendMocks(resend: resendSettings());

    $this->artisan('resend:attach', [
        'environment' => 'env-1',
        '--from-address' => 'team@example.com',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent()->attach ?? null)->toBeNull();
});

it('fails when the organization has no verified sending domains', function () {
    $sent = setupResendMocks(domains: []);

    $this->artisan('resend:attach', [
        'environment' => 'env-1',
        '--from-address' => 'team@example.com',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent()->attach ?? null)->toBeNull();
});

it('builds the integration settings URL for the organization', function () {
    expect(Organization::createFromResponse(organizationResponse())->integrationsUrl())
        ->toBe(Cloud::baseUrl().'/org/my-org/settings/integrations');
});

it('fails cleanly when the organization does not have Resend', function () {
    $sent = setupResendMocks(domainsResponse: MockResponse::make(['message' => 'Not Found'], 404));

    $this->artisan('resend:attach', [
        'environment' => 'env-1',
        '--from-address' => 'team@example.com',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent()->attach ?? null)->toBeNull();
});

it('updates the sender and keeps the fields it was not given', function () {
    $sent = setupResendMocks(resend: resendSettings());

    $this->artisan('resend:update', [
        'environment' => 'env-1',
        '--from-address' => 'team@example.com',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent()->update['url'])->toEndWith('/environments/env-1/resend');
    expect($sent()->update['body'])->toBe([
        'from_address' => 'team@example.com',
        'from_name' => 'My App',
    ]);
});

it('refuses to update when Resend is not attached', function () {
    $sent = setupResendMocks();

    $this->artisan('resend:update', [
        'environment' => 'env-1',
        '--from-address' => 'team@example.com',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent()->update ?? null)->toBeNull();
});

it('requires --force to detach non-interactively', function () {
    $sent = setupResendMocks(resend: resendSettings());

    $this->artisan('resend:detach', [
        'environment' => 'env-1',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent()->detach ?? null)->toBeNull();
});

it('detaches Resend', function () {
    $sent = setupResendMocks(resend: resendSettings());

    $this->artisan('resend:detach', [
        'environment' => 'env-1',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent()->detach['url'])->toEndWith('/environments/env-1/resend');
});

it('lists the sending keys an environment can reuse', function () {
    setupResendMocks(keys: [['id' => 'key-1', 'name' => 'shared-key', 'environment_count' => 2]]);

    Artisan::call('resend:keys', ['environment' => 'env-1', '--json' => true]);

    expect(json_decode(Artisan::output(), true))->toBe([
        ['id' => 'key-1', 'name' => 'shared-key', 'environmentCount' => 2],
    ]);
});

it('lists sending domains with filters', function () {
    $sent = setupResendMocks();

    Artisan::call('resend:domains', ['--status' => 'verified', '--name' => 'example.com', '--json' => true]);

    expect($sent()->domains['query'])->toMatchArray(['filter[name]' => 'example.com', 'filter[status]' => 'verified']);
    expect(json_decode(Artisan::output(), true)[0])->toMatchArray([
        'id' => 'rd-1',
        'name' => 'example.com',
        'region' => 'us-east-1',
        'status' => 'verified',
    ]);
});

it('rebuilds an environment with Resend attached inside its application', function () {
    $application = Application::createFromResponse([
        'data' => createApplicationResponse(),
        'included' => [
            ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
            createEnvironmentResponse(['attributes' => ['resend' => resendSettings()]]),
        ],
    ]);

    expect($application->environments[0]->resend->sender())->toBe('My App <hello@example.com>');
});

/**
 * An application with two environments, where only the named ones have Resend attached.
 *
 * @param  list<string>  $attachedIds
 * @return Closure(): stdClass
 */
function setupResendPickerMocks(array $attachedIds): Closure
{
    $sent = new stdClass;

    $git = Mockery::mock(Git::class);
    $git->shouldReceive('isRepo')->andReturn(true);
    $git->shouldReceive('getRoot')->andReturn('/tmp/resend-picker-repo');
    $git->shouldReceive('remoteRepo')->andReturn('user/my-app');
    $git->shouldReceive('currentBranch')->andReturn('main');
    app()->instance(Git::class, $git);

    $environment = fn (string $id, string $name) => createEnvironmentResponse([
        'id' => $id,
        'attributes' => [
            'name' => $name,
            'resend' => in_array($id, $attachedIds, true) ? resendSettings() : null,
        ],
    ]);

    $environments = [$environment('env-1', 'production'), $environment('env-2', 'staging')];

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make([
            'data' => [createApplicationResponse([
                'relationships' => ['environments' => ['data' => [
                    ['id' => 'env-1', 'type' => 'environments'],
                    ['id' => 'env-2', 'type' => 'environments'],
                ]]],
            ])],
            'included' => [
                ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
                ...$environments,
            ],
            'links' => ['next' => null],
        ], 200),
        GetEnvironmentRequest::class => function (PendingRequest $request) use ($environments) {
            $id = Str::afterLast($request->getUrl(), '/');

            return MockResponse::make(['data' => collect($environments)->firstWhere('id', $id)], 200);
        },
        ListResendDomainsRequest::class => MockResponse::make(['data' => [resendDomainResponse()], 'links' => ['next' => null]], 200),
        ListResendSendingKeysRequest::class => MockResponse::make(['data' => []], 200),
        AttachResendRequest::class => function (PendingRequest $request) use ($sent) {
            $sent->attach = $request->getUrl();

            return MockResponse::make(['data' => createEnvironmentResponse(['attributes' => ['resend' => resendSettings()]])], 200);
        },
        UpdateResendRequest::class => function (PendingRequest $request) use ($sent) {
            $sent->update = $request->getUrl();

            return MockResponse::make(['data' => createEnvironmentResponse(['attributes' => ['resend' => resendSettings()]])], 200);
        },
        DetachResendRequest::class => function (PendingRequest $request) use ($sent) {
            $sent->detach = $request->getUrl();

            return MockResponse::make('', 204);
        },
    ]);

    return fn () => $sent;
}

it('detaches from the only environment with Resend when none is given', function () {
    $sent = setupResendPickerMocks(attachedIds: ['env-2']);

    $this->artisan('resend:detach', ['--force' => true, '--no-interaction' => true])->assertSuccessful();

    expect($sent()->detach)->toEndWith('/environments/env-2/resend');
});

it('attaches to the only environment without Resend when none is given', function () {
    $sent = setupResendPickerMocks(attachedIds: ['env-1']);

    $this->artisan('resend:attach', ['--from-address' => 'team@example.com', '--no-interaction' => true])->assertSuccessful();

    expect($sent()->attach)->toEndWith('/environments/env-2/resend');
});

it('asks for an environment when more than one has Resend', function () {
    $sent = setupResendPickerMocks(attachedIds: ['env-1', 'env-2']);

    $this->artisan('resend:update', ['--from-address' => 'team@example.com', '--no-interaction' => true])->assertFailed();

    expect($sent()->update ?? null)->toBeNull();
});

it('fails when no environment has Resend to detach', function () {
    $sent = setupResendPickerMocks(attachedIds: []);

    $this->artisan('resend:detach', ['--force' => true, '--no-interaction' => true])->assertFailed();

    expect($sent()->detach ?? null)->toBeNull();
});
