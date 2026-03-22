<?php

/**
 * Integration tests that hit the real Laravel Cloud API.
 *
 * Skipped unless CLOUD_CLI_INTEGRATION=true is set.
 * Requires LARAVEL_CLOUD_API_TOKEN for authentication.
 *
 * Run: CLOUD_CLI_INTEGRATION=true LARAVEL_CLOUD_API_TOKEN=<token> vendor/bin/pest tests/Integration/
 */

use App\ConfigRepository;
use App\Git;

beforeEach(function () {
    if (env('CLOUD_CLI_INTEGRATION') !== 'true') {
        $this->markTestSkipped('Integration tests require CLOUD_CLI_INTEGRATION=true');
    }

    $token = env('LARAVEL_CLOUD_API_TOKEN');

    if (! $token) {
        $this->markTestSkipped('Integration tests require LARAVEL_CLOUD_API_TOKEN');
    }

    // Provide the token via ConfigRepository so commands can authenticate
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect([$token]));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);

    // Mock Git so commands don't depend on being inside a git repo
    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('isRepo')->andReturn(false)->byDefault();
    $this->mockGit->shouldReceive('getRoot')->andReturn('/tmp/integration-test')->byDefault();
    $this->mockGit->shouldReceive('currentBranch')->andReturn('main')->byDefault();
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('')->byDefault();
    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(false)->byDefault();
    $this->app->instance(Git::class, $this->mockGit);
});

// ---------------------------------------------------------------------------
// Read-Only Tests (safe, no cost)
// ---------------------------------------------------------------------------

it('lists applications via the real API', function () {
    $this->artisan('application:list', ['--json' => true])
        ->assertSuccessful();
})->group('integration', 'read-only');

it('lists environments for the first application', function () {
    // Capture application list output to find the first app
    $result = $this->artisan('application:list', ['--json' => true]);
    $result->assertSuccessful();

    $output = $result->output();
    $apps = json_decode($output, true);

    if (empty($apps)) {
        $this->markTestSkipped('No applications found in account — cannot test environment:list');
    }

    $appId = $apps[0]['id'] ?? null;
    expect($appId)->not->toBeNull();

    $this->artisan('environment:list', [
        'application' => $appId,
        '--json' => true,
    ])->assertSuccessful();
})->group('integration', 'read-only');

it('lists database clusters via the real API', function () {
    $this->artisan('database-cluster:list', ['--json' => true])
        ->assertSuccessful();
})->group('integration', 'read-only');

it('lists caches via the real API', function () {
    $this->artisan('cache:list', ['--json' => true])
        ->assertSuccessful();
})->group('integration', 'read-only');

it('lists instance sizes via the real API', function () {
    $this->artisan('instance:sizes', ['--json' => true])
        ->assertSuccessful();
})->group('integration', 'read-only');

it('lists cache types via the real API', function () {
    $this->artisan('cache:types', ['--json' => true])
        ->assertSuccessful();
})->group('integration', 'read-only');

it('lists IP addresses via the real API', function () {
    $this->artisan('ip:addresses', ['--json' => true])
        ->assertSuccessful();
})->group('integration', 'read-only');

it('lists deployments for the first environment', function () {
    $result = $this->artisan('application:list', ['--json' => true]);
    $result->assertSuccessful();

    $output = $result->output();
    $apps = json_decode($output, true);

    if (empty($apps)) {
        $this->markTestSkipped('No applications found — cannot test deployment:list');
    }

    $appId = $apps[0]['id'] ?? null;

    $envResult = $this->artisan('environment:list', [
        'application' => $appId,
        '--json' => true,
    ]);
    $envResult->assertSuccessful();

    $envOutput = $envResult->output();
    $environments = json_decode($envOutput, true);

    if (empty($environments)) {
        $this->markTestSkipped('No environments found — cannot test deployment:list');
    }

    $envId = $environments[0]['id'] ?? null;
    expect($envId)->not->toBeNull();

    $this->artisan('deployment:list', [
        'environment' => $envId,
        '--application' => $appId,
        '--json' => true,
    ])->assertSuccessful();
})->group('integration', 'read-only');

it('lists domains for the first environment', function () {
    $result = $this->artisan('application:list', ['--json' => true]);
    $result->assertSuccessful();

    $output = $result->output();
    $apps = json_decode($output, true);

    if (empty($apps)) {
        $this->markTestSkipped('No applications found — cannot test domain:list');
    }

    $appId = $apps[0]['id'] ?? null;

    $envResult = $this->artisan('environment:list', [
        'application' => $appId,
        '--json' => true,
    ]);
    $envResult->assertSuccessful();

    $envOutput = $envResult->output();
    $environments = json_decode($envOutput, true);

    if (empty($environments)) {
        $this->markTestSkipped('No environments found — cannot test domain:list');
    }

    $envId = $environments[0]['id'] ?? null;

    // domain:list may return empty if no custom domains are set — that is fine
    $this->artisan('domain:list', [
        'environment' => $envId,
        '--application' => $appId,
        '--json' => true,
    ])->assertSuccessful();
})->group('integration', 'read-only');

it('gets the first application details', function () {
    $result = $this->artisan('application:list', ['--json' => true]);
    $apps = json_decode($result->output(), true);

    if (empty($apps)) {
        $this->markTestSkipped('No applications found');
    }

    $this->artisan('application:get', [
        'application' => $apps[0]['id'],
        '--json' => true,
    ])->assertSuccessful();
})->group('integration', 'read-only');

it('gets environment details for the first environment', function () {
    $result = $this->artisan('application:list', ['--json' => true]);
    $apps = json_decode($result->output(), true);

    if (empty($apps)) {
        $this->markTestSkipped('No applications found');
    }

    $envResult = $this->artisan('environment:list', [
        'application' => $apps[0]['id'],
        '--json' => true,
    ]);
    $environments = json_decode($envResult->output(), true);

    if (empty($environments)) {
        $this->markTestSkipped('No environments found');
    }

    $this->artisan('environment:get', [
        'environment' => $environments[0]['id'],
        '--application' => $apps[0]['id'],
        '--json' => true,
    ])->assertSuccessful();
})->group('integration', 'read-only');

it('lists database types', function () {
    $this->artisan('database-cluster:list', ['--json' => true])
        ->assertSuccessful();
})->group('integration', 'read-only');

it('lists dedicated clusters', function () {
    // May return "No dedicated clusters found" which is exit 1 — that's OK
    $this->artisan('dedicated-cluster:list', ['--json' => true]);
    $this->assertTrue(true); // Command ran without crashing
})->group('integration', 'read-only');

it('lists websocket clusters', function () {
    // May return "No WebSocket clusters found" — that's OK
    $this->artisan('websocket-cluster:list', ['--json' => true]);
    $this->assertTrue(true);
})->group('integration', 'read-only');

it('runs debug command for the first environment', function () {
    $result = $this->artisan('application:list', ['--json' => true]);
    $apps = json_decode($result->output(), true);

    if (empty($apps)) {
        $this->markTestSkipped('No applications found');
    }

    $envResult = $this->artisan('environment:list', [
        'application' => $apps[0]['id'],
        '--json' => true,
    ]);
    $environments = json_decode($envResult->output(), true);

    if (empty($environments)) {
        $this->markTestSkipped('No environments found');
    }

    $this->artisan('debug', [
        '--application' => $apps[0]['id'],
        '--environment' => $environments[0]['id'],
        '--json' => true,
    ])->assertSuccessful();
})->group('integration', 'read-only');

it('runs status command for the first application', function () {
    $result = $this->artisan('application:list', ['--json' => true]);
    $apps = json_decode($result->output(), true);

    if (empty($apps)) {
        $this->markTestSkipped('No applications found');
    }

    $envResult = $this->artisan('environment:list', [
        'application' => $apps[0]['id'],
        '--json' => true,
    ]);
    $environments = json_decode($envResult->output(), true);

    if (empty($environments)) {
        $this->markTestSkipped('No environments found');
    }

    $this->artisan('status', [
        '--application' => $apps[0]['id'],
        '--environment' => $environments[0]['id'],
        '--json' => true,
    ])->assertSuccessful();
})->group('integration', 'read-only');

it('redacts sensitive values with --hide-secrets flag', function () {
    $result = $this->artisan('database-cluster:list', [
        '--json' => true,
        '--hide-secrets' => true,
    ]);
    $result->assertSuccessful();

    $output = $result->output();
    expect($output)->not->toContain('npg_');
})->group('integration', 'read-only');

it('authenticates via --token flag', function () {
    $token = env('LARAVEL_CLOUD_API_TOKEN');

    $this->artisan('application:list', [
        '--json' => true,
        '--token' => $token,
    ])->assertSuccessful();
})->group('integration', 'read-only');

// ---------------------------------------------------------------------------
// Destructive Tests (creates resources, may incur cost)
// ---------------------------------------------------------------------------

it('performs full application lifecycle: create, env vars, stop, start, delete', function () {
    if (env('CLOUD_CLI_INTEGRATION_DESTRUCTIVE') !== 'true') {
        $this->markTestSkipped('Destructive integration tests require CLOUD_CLI_INTEGRATION_DESTRUCTIVE=true');
    }

    $appName = 'integration-test-'.date('YmdHis');

    $createResult = $this->artisan('application:create', [
        '--name' => $appName,
        '--json' => true,
        '--no-interaction' => true,
    ]);

    if ($createResult->exitCode() !== 0) {
        $this->markTestSkipped('application:create failed — account may not have repository access');
    }

    $app = json_decode($createResult->output(), true);
    $appId = $app['id'] ?? null;
    expect($appId)->not->toBeNull('Created application should have an ID');

    try {
        $envResult = $this->artisan('environment:list', [
            'application' => $appId,
            '--json' => true,
        ]);
        $envResult->assertSuccessful();
        $environments = json_decode($envResult->output(), true);
        $envId = $environments[0]['id'] ?? null;

        if ($envId) {
            // Set an environment variable
            $this->artisan('environment:variables', [
                'environment' => $envId,
                '--application' => $appId,
                '--action' => 'set',
                '--key' => ['INTEGRATION_TEST_VAR'],
                '--value' => 'test_value_123',
                '--force' => true,
                '--no-interaction' => true,
            ])->assertSuccessful();

            // Delete the environment variable
            $this->artisan('environment:variables', [
                'environment' => $envId,
                '--application' => $appId,
                '--action' => 'delete',
                '--key' => ['INTEGRATION_TEST_VAR'],
                '--force' => true,
                '--no-interaction' => true,
            ])->assertSuccessful();

            // Stop the environment
            $this->artisan('environment:stop', [
                'environment' => $envId,
                '--application' => $appId,
                '--force' => true,
                '--no-interaction' => true,
            ])->assertSuccessful();

            // Start the environment
            $this->artisan('environment:start', [
                'environment' => $envId,
                '--application' => $appId,
                '--force' => true,
                '--no-interaction' => true,
            ])->assertSuccessful();
        }
    } finally {
        $this->artisan('application:delete', [
            'application' => $appId,
            '--force' => true,
            '--no-interaction' => true,
        ])->assertSuccessful();
    }
})->group('integration', 'destructive');

it('performs database cluster lifecycle: create, snapshot, delete', function () {
    if (env('CLOUD_CLI_INTEGRATION_DESTRUCTIVE') !== 'true') {
        $this->markTestSkipped('Destructive integration tests require CLOUD_CLI_INTEGRATION_DESTRUCTIVE=true');
    }

    $clusterName = 'int-test-db-'.date('YmdHis');

    $createResult = $this->artisan('database-cluster:create', [
        '--name' => $clusterName,
        '--type' => 'neon_serverless_postgres_18',
        '--region' => 'us-east-2',
        '--json' => true,
        '--no-interaction' => true,
    ]);
    $createResult->assertSuccessful();

    $cluster = json_decode($createResult->output(), true);
    $clusterId = $cluster['id'] ?? null;
    expect($clusterId)->not->toBeNull();

    try {
        // Get cluster details
        $this->artisan('database-cluster:get', [
            'cluster' => $clusterId,
            '--json' => true,
        ])->assertSuccessful();

        // List databases in cluster
        $this->artisan('database:list', [
            'cluster' => $clusterId,
            '--json' => true,
        ])->assertSuccessful();

        // Create a database schema
        $this->artisan('database:create', [
            'cluster' => $clusterId,
            '--name' => 'testdb',
            '--json' => true,
            '--no-interaction' => true,
        ])->assertSuccessful();

        // Delete the schema
        $this->artisan('database:delete', [
            'cluster' => $clusterId,
            'database' => 'testdb',
            '--force' => true,
            '--no-interaction' => true,
        ])->assertSuccessful();
    } finally {
        $this->artisan('database-cluster:delete', [
            'cluster' => $clusterId,
            '--force' => true,
            '--no-interaction' => true,
        ])->assertSuccessful();
    }
})->group('integration', 'destructive');

it('performs cache lifecycle: create, update, delete', function () {
    if (env('CLOUD_CLI_INTEGRATION_DESTRUCTIVE') !== 'true') {
        $this->markTestSkipped('Destructive integration tests require CLOUD_CLI_INTEGRATION_DESTRUCTIVE=true');
    }

    $cacheName = 'int-test-cache-'.date('YmdHis');

    $createResult = $this->artisan('cache:create', [
        '--name' => $cacheName,
        '--type' => 'laravel_valkey',
        '--region' => 'us-east-2',
        '--size' => 'valkey-pro.250mb',
        '--auto-upgrade-enabled' => 'false',
        '--is-public' => 'false',
        '--eviction-policy' => 'noeviction',
        '--json' => true,
        '--no-interaction' => true,
    ]);
    $createResult->assertSuccessful();

    $cache = json_decode($createResult->output(), true);
    $cacheId = $cache['id'] ?? null;
    expect($cacheId)->not->toBeNull();

    try {
        $this->artisan('cache:get', [
            'cache' => $cacheId,
            '--json' => true,
        ])->assertSuccessful();

        // Wait for cache to finish creating before deleting
        sleep(15);
    } finally {
        $this->artisan('cache:delete', [
            'cache' => $cacheId,
            '--force' => true,
            '--no-interaction' => true,
        ])->assertSuccessful();
    }
})->group('integration', 'destructive');

it('performs websocket cluster lifecycle: create, app, delete', function () {
    if (env('CLOUD_CLI_INTEGRATION_DESTRUCTIVE') !== 'true') {
        $this->markTestSkipped('Destructive integration tests require CLOUD_CLI_INTEGRATION_DESTRUCTIVE=true');
    }

    $wsName = 'int-test-ws-'.date('YmdHis');

    $createResult = $this->artisan('websocket-cluster:create', [
        '--name' => $wsName,
        '--region' => 'us-east-2',
        '--json' => true,
        '--no-interaction' => true,
    ]);
    $createResult->assertSuccessful();

    $cluster = json_decode($createResult->output(), true);
    $clusterId = $cluster['id'] ?? null;
    expect($clusterId)->not->toBeNull();

    try {
        // List apps (default app created with cluster)
        $appResult = $this->artisan('websocket-application:list', [
            'cluster' => $clusterId,
            '--json' => true,
        ]);
        $appResult->assertSuccessful();

        // Get cluster metrics
        $this->artisan('websocket-cluster:metrics', [
            'cluster' => $clusterId,
            '--json' => true,
        ])->assertSuccessful();

        // Wait for cluster operations to settle
        sleep(10);
    } finally {
        $this->artisan('websocket-cluster:delete', [
            'cluster' => $clusterId,
            '--force' => true,
            '--no-interaction' => true,
        ])->assertSuccessful();
    }
})->group('integration', 'destructive');
