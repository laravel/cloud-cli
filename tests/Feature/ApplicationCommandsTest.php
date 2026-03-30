<?php

use App\Client\Resources\Applications\CreateApplicationRequest;
use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Applications\UpdateApplicationRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Meta\ListRegionsRequest;
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
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

// ---- application:list ----

it('lists applications successfully in interactive mode', function () {
    Prompt::fake();

    setupApplicationListMocks();

    $this->artisan('application:list')
        ->assertSuccessful();
});

it('lists applications successfully in non-interactive mode and outputs JSON', function () {
    Prompt::fake();
    setupApplicationListMocks();

    $this->artisan('application:list', ['--no-interaction' => true])->assertSuccessful()->expectsOutputToContain('My App');

    // $result->assertSuccessful();
    // dd(Prompt::content());
    // $output = $result->output();
    // expect(json_decode($output, true))->toBeArray();
});

// it('lists applications with --json and outputs JSON', function () {
//     setupApplicationListMocks();

//     $result = $this->artisan('application:list', ['--json' => true]);

//     $result->assertSuccessful();
//     $output = $result->output();
//     expect(json_decode($output, true))->toBeArray();
// });

// it('returns failure when list is empty in interactive mode', function () {
//     Prompt::fake();

//     setupApplicationListMocks([]);

//     $this->artisan('application:list')
//         ->assertFailed();
// });

// it('returns success with empty JSON array when list is empty with --json', function () {
//     setupApplicationListMocks([]);

//     $result = $this->artisan('application:list', ['--json' => true]);

//     $result->assertSuccessful();
//     $decoded = json_decode($result->output(), true);
//     expect($decoded)->toBeArray()->toBeEmpty();
// });

// it('returns non-zero exit and error when list API returns 500 in non-interactive mode', function () {
//     setupApplicationListMocks([], 500);

//     $result = $this->artisan('application:list', ['--no-interaction' => true]);

//     $result->assertFailed();
// });

// it('returns failure when list API returns 500 in interactive mode', function () {
//     Prompt::fake();

//     setupApplicationListMocks([], 500);

//     $this->artisan('application:list')
//         ->assertFailed();
// });

// // ---- application:get ----

// it('gets application by ID successfully in interactive mode', function () {
//     Prompt::fake();

//     MockClient::global([
//         GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
//         GetApplicationRequest::class => MockResponse::make([
//             'data' => createApplicationResponse(),
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//         ], 200),
//     ]);

//     $this->artisan('application:get', ['application' => 'app-123'])
//         ->assertSuccessful();
// });

// it('gets application by ID with --json and outputs JSON', function () {
//     MockClient::global([
//         GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
//         GetApplicationRequest::class => MockResponse::make([
//             'data' => createApplicationResponse(),
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//         ], 200),
//     ]);

//     $result = $this->artisan('application:get', ['application' => 'app-123', '--json' => true]);

//     $result->assertSuccessful();
//     $decoded = json_decode($result->output(), true);
//     expect($decoded)->toBeArray()->toHaveKey('id', 'app-123');
// });

// it('gets application by name in non-interactive mode', function () {
//     MockClient::global([
//         GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
//         ListApplicationsRequest::class => MockResponse::make([
//             'data' => [createApplicationResponse()],
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//             'links' => ['next' => null],
//         ], 200),
//         GetApplicationRequest::class => MockResponse::make([
//             'data' => createApplicationResponse(),
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//         ], 200),
//     ]);

//     $result = $this->artisan('application:get', ['application' => 'My App', '--no-interaction' => true]);

//     $result->assertSuccessful();
// });

// it('returns failure with JSON error when application not found in non-interactive mode', function () {
//     MockClient::global([
//         GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
//         ListApplicationsRequest::class => MockResponse::make([
//             'data' => [],
//             'included' => [],
//             'links' => ['next' => null],
//         ], 200),
//     ]);

//     $result = $this->artisan('application:get', ['application' => 'nonexistent', '--no-interaction' => true]);

//     $result->assertFailed();
//     $decoded = json_decode($result->output(), true);
//     expect($decoded)->toBeArray()->toHaveKey('message');
// });

// it('returns failure with JSON error when application argument missing in non-interactive mode', function () {
//     MockClient::global([
//         GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
//         ListApplicationsRequest::class => MockResponse::make([
//             'data' => [],
//             'included' => [],
//             'links' => ['next' => null],
//         ], 200),
//     ]);

//     $result = $this->artisan('application:get', ['--no-interaction' => true]);

//     $result->assertFailed();
//     $decoded = json_decode($result->output(), true);
//     expect($decoded)->toBeArray()->toHaveKey('message');
// });

// it('returns failure when get by ID returns 404 and list has no matching app', function () {
//     MockClient::global([
//         GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
//         GetApplicationRequest::class => MockResponse::make(['message' => 'Not found'], 404),
//         ListApplicationsRequest::class => MockResponse::make([
//             'data' => [],
//             'included' => [],
//             'links' => ['next' => null],
//         ], 200),
//     ]);

//     $result = $this->artisan('application:get', ['application' => 'app-123', '--no-interaction' => true]);

//     $result->assertFailed();
//     $decoded = json_decode($result->output(), true);
//     expect($decoded)->toBeArray()->toHaveKey('message');
// });

// it('gets application interactively when no argument given but only one app exists', function () {
//     Prompt::fake();

//     MockClient::global([
//         GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
//         ListApplicationsRequest::class => MockResponse::make([
//             'data' => [createApplicationResponse()],
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//             'links' => ['next' => null],
//         ], 200),
//     ]);

//     $this->artisan('application:get')
//         ->assertSuccessful();
// });

// // ---- application:create ----

// it('creates application successfully in non-interactive mode with all options', function () {
//     MockClient::global([
//         GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
//         ListRegionsRequest::class => MockResponse::make(regionsResponse(), 200),
//         CreateApplicationRequest::class => MockResponse::make([
//             'data' => createApplicationResponse(),
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//         ], 200),
//         GetApplicationRequest::class => MockResponse::make([
//             'data' => createApplicationResponse(),
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//         ], 200),
//     ]);

//     $result = $this->artisan('application:create', [
//         '--name' => 'My App',
//         '--repository' => 'user/my-app',
//         '--region' => 'us-east-1',
//         '--no-interaction' => true,
//     ]);

//     $result->assertSuccessful();
// });

// it('creates application with --json and outputs JSON', function () {
//     MockClient::global([
//         GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
//         ListRegionsRequest::class => MockResponse::make(regionsResponse(), 200),
//         CreateApplicationRequest::class => MockResponse::make([
//             'data' => createApplicationResponse(),
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//         ], 200),
//         GetApplicationRequest::class => MockResponse::make([
//             'data' => createApplicationResponse(),
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//         ], 200),
//     ]);

//     $result = $this->artisan('application:create', [
//         '--name' => 'My App',
//         '--repository' => 'user/my-app',
//         '--region' => 'us-east-1',
//         '--json' => true,
//     ]);

//     $result->assertSuccessful();
//     $decoded = json_decode($result->output(), true);
//     expect($decoded)->toBeArray();
// });

// it('returns failure with JSON validation errors when API returns 422 on create', function () {
//     MockClient::global([
//         GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
//         ListRegionsRequest::class => MockResponse::make(regionsResponse(), 200),
//         CreateApplicationRequest::class => MockResponse::make([
//             'message' => 'Validation failed',
//             'errors' => ['name' => ['Name has already been taken.']],
//         ], 422),
//     ]);

//     $result = $this->artisan('application:create', [
//         '--name' => 'Taken',
//         '--repository' => 'user/my-app',
//         '--region' => 'us-east-1',
//         '--json' => true,
//     ]);

//     $result->assertFailed();
//     $output = $result->output();
//     expect($output)->not->toBeEmpty();
//     $decoded = json_decode($output, true);
//     expect($decoded)->toBeArray();
// });

// it('returns failure when create API returns 500', function () {
//     MockClient::global([
//         GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
//         ListRegionsRequest::class => MockResponse::make(regionsResponse(), 200),
//         CreateApplicationRequest::class => MockResponse::make(['message' => 'Server error'], 500),
//     ]);

//     $result = $this->artisan('application:create', [
//         '--name' => 'My App',
//         '--repository' => 'user/my-app',
//         '--region' => 'us-east-1',
//         '--no-interaction' => true,
//     ]);

//     $result->assertFailed();
// });

// // ---- application:update ----

// it('updates application successfully in non-interactive mode with options and --force', function () {
//     MockClient::global([
//         GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
//         GetApplicationRequest::class => MockResponse::make([
//             'data' => createApplicationResponse(),
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//         ], 200),
//         UpdateApplicationRequest::class => MockResponse::make([
//             'data' => createApplicationResponse(['attributes' => ['name' => 'New Name', 'slug' => 'my-app', 'region' => 'us-east-1']]),
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//         ], 200),
//     ]);

//     $result = $this->artisan('application:update', [
//         'application' => 'app-123',
//         '--name' => 'New Name',
//         '--force' => true,
//         '--no-interaction' => true,
//     ]);

//     $result->assertSuccessful();
// });

// it('updates application with --json and outputs JSON', function () {
//     MockClient::global([
//         GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
//         GetApplicationRequest::class => MockResponse::make([
//             'data' => createApplicationResponse(),
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//         ], 200),
//         UpdateApplicationRequest::class => MockResponse::make([
//             'data' => createApplicationResponse(),
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//         ], 200),
//     ]);

//     $result = $this->artisan('application:update', [
//         'application' => 'app-123',
//         '--name' => 'New Name',
//         '--force' => true,
//         '--json' => true,
//     ]);

//     $result->assertSuccessful();
//     $decoded = json_decode($result->output(), true);
//     expect($decoded)->toBeArray();
// });

// it('returns failure with JSON error when no fields to update in non-interactive mode', function () {
//     MockClient::global([
//         GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
//         GetApplicationRequest::class => MockResponse::make([
//             'data' => createApplicationResponse(),
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//         ], 200),
//     ]);

//     $result = $this->artisan('application:update', [
//         'application' => 'app-123',
//         '--no-interaction' => true,
//     ]);

//     $result->assertFailed();
//     $decoded = json_decode($result->output(), true);
//     expect($decoded)->toBeArray()->toHaveKey('message');
// });

// it('returns failure with JSON error when application not found on update', function () {
//     MockClient::global([
//         GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
//         ListApplicationsRequest::class => MockResponse::make([
//             'data' => [],
//             'included' => [],
//             'links' => ['next' => null],
//         ], 200),
//     ]);

//     $result = $this->artisan('application:update', [
//         'application' => 'nonexistent',
//         '--name' => 'New Name',
//         '--no-interaction' => true,
//     ]);

//     $result->assertFailed();
//     $decoded = json_decode($result->output(), true);
//     expect($decoded)->toBeArray()->toHaveKey('message');
// });

// it('returns failure when user cancels confirm in interactive update', function () {
//     Prompt::fake(['Update the application?' => false]);

//     MockClient::global([
//         GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
//         GetApplicationRequest::class => MockResponse::make([
//             'data' => createApplicationResponse(),
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//         ], 200),
//     ]);

//     $this->artisan('application:update', [
//         'application' => 'app-123',
//         '--name' => 'New Name',
//     ])->assertFailed();
// });

// it('updates application by name in non-interactive mode', function () {
//     MockClient::global([
//         GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
//         ListApplicationsRequest::class => MockResponse::make([
//             'data' => [createApplicationResponse()],
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//             'links' => ['next' => null],
//         ], 200),
//         GetApplicationRequest::class => MockResponse::make([
//             'data' => createApplicationResponse(),
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//         ], 200),
//         UpdateApplicationRequest::class => MockResponse::make([
//             'data' => createApplicationResponse(['attributes' => ['name' => 'New Name', 'slug' => 'my-app', 'region' => 'us-east-1']]),
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//         ], 200),
//     ]);

//     $result = $this->artisan('application:update', [
//         'application' => 'My App',
//         '--name' => 'New Name',
//         '--force' => true,
//         '--no-interaction' => true,
//     ]);

//     $result->assertSuccessful();
// });

// it('returns failure when update API returns 422', function () {
//     MockClient::global([
//         GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
//         GetApplicationRequest::class => MockResponse::make([
//             'data' => createApplicationResponse(),
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//         ], 200),
//         UpdateApplicationRequest::class => MockResponse::make([
//             'message' => 'Validation failed',
//             'errors' => ['name' => ['Name has already been taken.']],
//         ], 422),
//     ]);

//     $result = $this->artisan('application:update', [
//         'application' => 'app-123',
//         '--name' => 'Taken',
//         '--force' => true,
//         '--no-interaction' => true,
//     ]);

//     $result->assertFailed();
// });

// it('returns failure when update API returns 500', function () {
//     MockClient::global([
//         GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
//         GetApplicationRequest::class => MockResponse::make([
//             'data' => createApplicationResponse(),
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//         ], 200),
//         UpdateApplicationRequest::class => MockResponse::make(['message' => 'Server error'], 500),
//     ]);

//     $result = $this->artisan('application:update', [
//         'application' => 'app-123',
//         '--name' => 'New Name',
//         '--force' => true,
//         '--no-interaction' => true,
//     ]);

//     $result->assertFailed();
// });

// it('updates application with --slug and --repository options', function () {
//     MockClient::global([
//         GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
//         GetApplicationRequest::class => MockResponse::make([
//             'data' => createApplicationResponse(),
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//         ], 200),
//         UpdateApplicationRequest::class => MockResponse::make([
//             'data' => createApplicationResponse(['attributes' => ['name' => 'My App', 'slug' => 'new-slug', 'region' => 'us-east-1']]),
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//         ], 200),
//     ]);

//     $result = $this->artisan('application:update', [
//         'application' => 'app-123',
//         '--slug' => 'new-slug',
//         '--repository' => 'user/other-repo',
//         '--force' => true,
//         '--no-interaction' => true,
//     ]);

//     $result->assertSuccessful();
// });

// it('updates application successfully in interactive mode when user confirms', function () {
//     Prompt::fake(['Update the application?' => true]);

//     MockClient::global([
//         GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
//         GetApplicationRequest::class => MockResponse::make([
//             'data' => createApplicationResponse(),
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//         ], 200),
//         UpdateApplicationRequest::class => MockResponse::make([
//             'data' => createApplicationResponse(['attributes' => ['name' => 'New Name', 'slug' => 'my-app', 'region' => 'us-east-1']]),
//             'included' => [
//                 ['id' => 'org-1', 'type' => 'organizations', 'attributes' => ['name' => 'My Org', 'slug' => 'my-org']],
//                 ['id' => 'env-1', 'type' => 'environments', 'attributes' => ['name' => 'production', 'slug' => 'production', 'vanity_domain' => 'my-app.cloud.laravel.com', 'status' => 'running', 'php_major_version' => '8.3']],
//             ],
//         ], 200),
//     ]);

//     $this->artisan('application:update', [
//         'application' => 'app-123',
//         '--name' => 'New Name',
//     ])->assertSuccessful();
// });
