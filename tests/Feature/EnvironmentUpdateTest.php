<?php

use App\Client\Requests\UpdateEnvironmentRequestData;
use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\UpdateEnvironmentRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use Illuminate\Support\Facades\Artisan;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Tests\Fixtures\InteractiveEnvironmentUpdateCommand;

beforeEach(function () {
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);

    $this->sent = new stdClass;
    $this->sent->body = null;

    $environment = ['data' => createEnvironmentResponse(['attributes' => [
        'framework' => 'laravel',
        'language' => 'php',
        'serving' => 'process',
        'start_command' => null,
        'hibernation_wake_up_interval' => 60,
        'timeout' => 30,
        'shutdown_timeout' => 45,
        'post_deploy_command' => 'php artisan cache:clear',
        'uses_http_basic_auth' => true,
        'uses_read_only_build_token' => true,
        'cluster_id' => 'cluster-1',
        'aws_role_arn' => 'arn:aws:iam::123456789012:role/laravel-cloud',
        'aws_trust_policy' => '{"Version":"2012-10-17","Statement":[]}',
        'internal_networking' => [
            'enabled' => true,
            'hostname' => 'production.internal',
            'access_level' => 'specific',
            'environment_ids' => ['env-2'],
        ],
        'network_settings' => [
            'response_headers' => [
                'frame' => 'deny',
                'content_type' => 'nosniff',
                'hsts' => ['max_age' => 31536000, 'include_subdomains' => true, 'preload' => false],
            ],
        ],
    ], 'relationships' => [
        'application' => ['data' => ['id' => 'app-123', 'type' => 'applications']],
        'modelGatewayKey' => ['data' => ['id' => 'mgk-1', 'type' => 'model_gateway_keys']],
    ]]), 'included' => [createApplicationResponse()]];

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetApplicationRequest::class => MockResponse::make(['data' => createApplicationResponse()], 200),
        GetEnvironmentRequest::class => function (PendingRequest $request) use ($environment) {
            $this->sent->include = $request->query()->get('include');

            return MockResponse::make($environment, 200);
        },
        UpdateEnvironmentRequest::class => function (PendingRequest $request) use ($environment) {
            $this->sent->body = $request->body()->all();

            return MockResponse::make($environment, 200);
        },
    ]);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function updateEnvironment(array $options): void
{
    test()->artisan('environment:update', [
        'environment' => 'env-1',
        '--no-interaction' => true,
        ...$options,
    ])->assertSuccessful();
}

it('sends the environment settings passed as options', function () {
    updateEnvironment([
        '--name' => 'staging',
        '--slug' => 'staging',
        '--color' => 'purple',
        '--php-version' => '8.4:1',
        '--node-version' => '22',
        '--timeout' => '45',
        '--shutdown-timeout' => '120',
        '--sleep-timeout' => '10',
        '--hibernation-wake-up-interval' => '1440',
        '--start-command' => 'node server.js',
        '--cache-strategy' => 'bypass',
        '--response-headers-frame' => 'sameorigin',
        '--response-headers-content-type' => 'none',
        '--response-headers-robots-tag' => 'noindex, nofollow',
    ]);

    expect($this->sent->body)->toBe([
        'name' => 'staging',
        'slug' => 'staging',
        'color' => 'purple',
        'timeout' => 45,
        'php_version' => '8.4:1',
        'node_version' => '22',
        'start_command' => 'node server.js',
        'sleep_timeout' => 10,
        'hibernation_wake_up_interval' => 1440,
        'shutdown_timeout' => 120,
        'cache_strategy' => 'bypass',
        'response_headers_frame' => 'sameorigin',
        'response_headers_content_type' => 'none',
        'response_headers_robots_tag' => 'noindex, nofollow',
    ]);
});

it('sends boolean options as booleans', function (string $option, string $field, string $value, bool $expected) {
    updateEnvironment(["--{$option}" => $value]);

    expect($this->sent->body)->toBe([$field => $expected]);
})->with([
    ['uses-push-to-deploy', 'uses_push_to_deploy', 'false', false],
    ['uses-deploy-hook', 'uses_deploy_hook', 'true', true],
    ['uses-read-only-build-token', 'uses_read_only_build_token', 'true', true],
    ['uses-vanity-domain', 'uses_vanity_domain', 'false', false],
    ['uses-octane', 'uses_octane', '0', false],
    ['uses-purge-edge-cache-on-deploy', 'uses_purge_edge_cache_on_deploy', '1', true],
    ['firewall-block-path', 'firewall_block_path', 'true', true],
    ['firewall-browser-integrity-check', 'firewall_browser_integrity_check', 'false', false],
]);

it('sends an explicit null when a nullable setting is cleared with an empty string', function (string $option, string $field) {
    updateEnvironment(["--{$option}" => '']);

    expect($this->sent->body)->toHaveKey($field)
        ->and($this->sent->body[$field])->toBeNull();
})->with([
    ['nightwatch-token', 'nightwatch_token'],
    ['hibernation-wake-up-interval', 'hibernation_wake_up_interval'],
    ['start-command', 'start_command'],
]);

it('sends the nightwatch token when one is given', function () {
    $token = str_repeat('a', 44);

    updateEnvironment(['--nightwatch-token' => $token]);

    expect($this->sent->body)->toBe(['nightwatch_token' => $token]);
});

it('fills the HSTS settings left out from the current environment', function () {
    updateEnvironment(['--hsts-preload' => 'true']);

    expect($this->sent->body)->toBe([
        'response_headers_hsts' => [
            'max_age' => 31536000,
            'include_subdomains' => true,
            'preload' => true,
        ],
    ]);
});

it('sends every HSTS setting passed', function () {
    updateEnvironment([
        '--hsts-max-age' => '0',
        '--hsts-include-subdomains' => 'false',
        '--hsts-preload' => 'false',
    ]);

    expect($this->sent->body['response_headers_hsts'])->toBe([
        'max_age' => 0,
        'include_subdomains' => false,
        'preload' => false,
    ]);
});

it('fails without sending anything when no options are given', function () {
    $this->artisan('environment:update', [
        'environment' => 'env-1',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($this->sent->body)->toBeNull();
});

it('sends the serving configuration with its nullable root', function () {
    $data = new UpdateEnvironmentRequestData(
        environmentId: 'env-1',
        serving: ['mode' => 'process', 'root' => null],
    );

    expect($data->toRequestData())->toBe(['serving' => ['mode' => 'process', 'root' => null]]);
});

it('exposes the runtime attributes of an environment', function () {
    Artisan::call('environment:get', [
        'environment' => 'env-1',
        '--json' => true,
        '--fields' => 'framework,language,serving,startCommand,hibernationWakeUpInterval,timeout,shutdownTimeout',
        '--no-interaction' => true,
    ]);

    expect(json_decode(Artisan::output(), true))->toBe([
        'framework' => 'laravel',
        'language' => 'php',
        'serving' => 'process',
        'startCommand' => null,
        'hibernationWakeUpInterval' => 60,
        'timeout' => 30,
        'shutdownTimeout' => 45,
    ]);
});

it('sends the post-deploy command', function () {
    updateEnvironment(['--post-deploy-command' => 'php artisan queue:restart']);

    expect($this->sent->body)->toBe(['post_deploy_command' => 'php artisan queue:restart']);
});

it('turns HTTP basic auth on with a password', function () {
    updateEnvironment(['--http-basic-auth-password' => 'hunter2']);

    expect($this->sent->body)->toBe([
        'uses_http_basic_auth' => true,
        'http_basic_auth_password' => 'hunter2',
    ]);
});

it('turns HTTP basic auth off with an empty password', function () {
    updateEnvironment(['--http-basic-auth-password' => '']);

    expect($this->sent->body)->toBe(['uses_http_basic_auth' => false]);
});

it('asks for a password when HTTP basic auth is turned on interactively', function () {
    Artisan::registerCommand(new InteractiveEnvironmentUpdateCommand);

    Prompt::fake([
        ...array_fill(0, 15, Key::DOWN), Key::SPACE, Key::ENTER,
        Key::ENTER,
        'h', 'u', 'n', 't', 'e', 'r', '2', Key::ENTER,
    ]);

    $this->artisan('test:interactive-environment-update', ['environment' => 'env-1'])->assertSuccessful();

    expect($this->sent->body)->toBe([
        'uses_http_basic_auth' => true,
        'http_basic_auth_password' => 'hunter2',
    ]);
});

it('turns HTTP basic auth off interactively without asking for a password', function () {
    Artisan::registerCommand(new InteractiveEnvironmentUpdateCommand);

    Prompt::fake([
        ...array_fill(0, 15, Key::DOWN), Key::SPACE, Key::ENTER,
        'n', Key::ENTER,
    ]);

    $this->artisan('test:interactive-environment-update', ['environment' => 'env-1'])->assertSuccessful();

    expect($this->sent->body)->toBe(['uses_http_basic_auth' => false]);
});

it('exposes the post-deploy command, HTTP basic auth and internal networking', function () {
    Artisan::call('environment:get', [
        'environment' => 'env-1',
        '--json' => true,
        '--fields' => 'postDeployCommand,usesHttpBasicAuth,internalNetworking',
        '--no-interaction' => true,
    ]);

    expect(json_decode(Artisan::output(), true))->toBe([
        'postDeployCommand' => 'php artisan cache:clear',
        'usesHttpBasicAuth' => true,
        'internalNetworking' => [
            'enabled' => true,
            'hostname' => 'production.internal',
            'accessLevel' => 'specific',
            'environmentIds' => ['env-2'],
        ],
    ]);
});

it('attaches a model gateway key', function () {
    updateEnvironment(['--model-gateway-key-id' => 'mgk-2']);

    expect($this->sent->body)->toBe(['model_gateway_key_id' => 'mgk-2']);
});

it('detaches the model gateway key with an empty string', function () {
    updateEnvironment(['--model-gateway-key-id' => '']);

    expect($this->sent->body)->toBe(['model_gateway_key_id' => '']);
});

it('exposes the attached model gateway key', function () {
    Artisan::call('environment:get', [
        'environment' => 'env-1',
        '--json' => true,
        '--fields' => 'modelGatewayKeyId',
        '--no-interaction' => true,
    ]);

    expect(json_decode(Artisan::output(), true))->toBe(['modelGatewayKeyId' => 'mgk-1'])
        ->and(explode(',', $this->sent->include))->toContain('modelGatewayKey');
});

it('sends the AWS role ARN', function () {
    updateEnvironment(['--aws-role-arn' => 'arn:aws:iam::123456789012:role/storefront']);

    expect($this->sent->body)->toBe(['aws_role_arn' => 'arn:aws:iam::123456789012:role/storefront']);
});

it('removes the AWS role with an empty string', function () {
    updateEnvironment(['--aws-role-arn' => '']);

    expect($this->sent->body)->toBe(['aws_role_arn' => null]);
});

it('exposes the dedicated cluster and AWS role', function () {
    Artisan::call('environment:get', [
        'environment' => 'env-1',
        '--json' => true,
        '--fields' => 'clusterId,awsRoleArn,awsTrustPolicy',
        '--no-interaction' => true,
    ]);

    expect(json_decode(Artisan::output(), true))->toBe([
        'clusterId' => 'cluster-1',
        'awsRoleArn' => 'arn:aws:iam::123456789012:role/laravel-cloud',
        'awsTrustPolicy' => '{"Version":"2012-10-17","Statement":[]}',
    ]);
});

it('exposes whether builds use a read-only token', function () {
    Artisan::call('environment:get', [
        'environment' => 'env-1',
        '--json' => true,
        '--fields' => 'usesReadOnlyBuildToken',
        '--no-interaction' => true,
    ]);

    expect(json_decode(Artisan::output(), true))->toBe(['usesReadOnlyBuildToken' => true]);
});

it('turns the read-only build token off interactively', function () {
    Artisan::registerCommand(new InteractiveEnvironmentUpdateCommand);

    Prompt::fake([
        ...array_fill(0, 12, Key::DOWN), Key::SPACE, Key::ENTER,
        'n', Key::ENTER,
    ]);

    $this->artisan('test:interactive-environment-update', ['environment' => 'env-1'])->assertSuccessful();

    expect($this->sent->body)->toBe(['uses_read_only_build_token' => false]);
});
