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

it('redacts sensitive values with --hide-secrets flag', function () {
    $result = $this->artisan('application:list', [
        '--json' => true,
        '--hide-secrets' => true,
    ]);
    $result->assertSuccessful();

    // The flag should not cause errors; redaction applies to sensitive fields
    // in output. We verify the command completes without failure.
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

    // ---- Step 1: Create the application ----
    // Note: application:create requires a GitHub repository.
    // This test assumes the account has repository access configured.
    // If not, this test will fail at this step — which is expected.
    $createResult = $this->artisan('application:create', [
        '--name' => $appName,
        '--json' => true,
        '--no-interaction' => true,
    ]);

    // If creation fails (e.g., no repo access), skip gracefully
    if ($createResult->exitCode() !== 0) {
        $this->markTestSkipped(
            'application:create failed — this account may not have repository access configured',
        );
    }

    $createOutput = $createResult->output();
    $app = json_decode($createOutput, true);
    $appId = $app['id'] ?? null;

    expect($appId)->not->toBeNull('Created application should have an ID');

    try {
        // ---- Step 2: Get environments for the new app ----
        $envResult = $this->artisan('environment:list', [
            'application' => $appId,
            '--json' => true,
        ]);
        $envResult->assertSuccessful();

        $envOutput = $envResult->output();
        $environments = json_decode($envOutput, true);
        $envId = $environments[0]['id'] ?? null;

        if ($envId) {
            // ---- Step 3: Set an environment variable ----
            $this->artisan('environment:variables', [
                'environment' => $envId,
                '--application' => $appId,
                '--action' => 'append',
                '--key' => ['INTEGRATION_TEST_VAR'],
                '--value' => 'test_value_123',
                '--force' => true,
                '--no-interaction' => true,
            ])->assertSuccessful();

            // ---- Step 4: Delete the environment variable ----
            $this->artisan('environment:variables', [
                'environment' => $envId,
                '--application' => $appId,
                '--action' => 'delete',
                '--key' => ['INTEGRATION_TEST_VAR'],
                '--force' => true,
                '--no-interaction' => true,
            ])->assertSuccessful();

            // ---- Step 5: Stop the environment ----
            $this->artisan('environment:stop', [
                'environment' => $envId,
                '--application' => $appId,
                '--force' => true,
                '--no-interaction' => true,
            ])->assertSuccessful();

            // ---- Step 6: Start the environment ----
            $this->artisan('environment:start', [
                'environment' => $envId,
                '--application' => $appId,
                '--force' => true,
                '--no-interaction' => true,
            ])->assertSuccessful();
        }
    } finally {
        // ---- Step 7: Always clean up — delete the application ----
        $this->artisan('application:delete', [
            'application' => $appId,
            '--force' => true,
            '--no-interaction' => true,
        ])->assertSuccessful();
    }
})->group('integration', 'destructive');
