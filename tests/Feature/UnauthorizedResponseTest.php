<?php

use App\Client\Connector;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Usage\GetUsageRequest;
use App\Commands\Usage;
use App\ConfigRepository;
use Illuminate\Support\Facades\Artisan;
use Saloon\Exceptions\Request\Statuses\UnauthorizedException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    config()->set('cloud.api_token', null);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['revoked-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function unauthorizedException(string $token = 'revoked-token', array|string $body = ['message' => 'Invalid API token']): UnauthorizedException
{
    $connector = new Connector($token);
    $connector->withMockClient(new MockClient([
        GetOrganizationRequest::class => MockResponse::make($body, 401),
    ]));

    try {
        $connector->send(new GetOrganizationRequest);
    } catch (UnauthorizedException $e) {
        return $e;
    }

    throw new RuntimeException('Expected the request to be rejected.');
}

function unauthorizedMessage(UnauthorizedException $e): string
{
    $method = new ReflectionMethod(Usage::class, 'unauthorizedMessage');

    return $method->invoke(app(Usage::class), $e);
}

/**
 * Headless errors are written straight to STDERR with fwrite, which Artisan's
 * test output never sees, so intercept the raw streams themselves.
 */
class CapturesStream extends php_user_filter
{
    /** @var array<string, string> */
    public static array $captured = [];

    public function filter($in, $out, &$consumed, bool $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            self::$captured[$this->params] = (self::$captured[$this->params] ?? '').$bucket->data;
            $consumed += $bucket->datalen;
        }

        // Nothing is passed on, so captured output doesn't leak into the test run's own.
        return PSFS_PASS_ON;
    }
}

/**
 * @return array{exitCode: int, stdout: string, stderr: string}
 */
function runCapturingOutput(string $command, array $parameters): array
{
    if (! in_array('captures-stream', stream_get_filters(), true)) {
        stream_filter_register('captures-stream', CapturesStream::class);
    }

    CapturesStream::$captured = [];
    $stdout = stream_filter_append(STDOUT, 'captures-stream', STREAM_FILTER_WRITE, 'stdout');
    $stderr = stream_filter_append(STDERR, 'captures-stream', STREAM_FILTER_WRITE, 'stderr');

    try {
        $exitCode = Artisan::call($command, $parameters);
    } finally {
        stream_filter_remove($stdout);
        stream_filter_remove($stderr);
    }

    return [
        'exitCode' => $exitCode,
        // Prompts and command output go through Artisan's buffer; anything else would hit STDOUT directly.
        'stdout' => Artisan::output().(CapturesStream::$captured['stdout'] ?? ''),
        'stderr' => CapturesStream::$captured['stderr'] ?? '',
    ];
}

it('fails cleanly instead of throwing when the API rejects the token', function () {
    MockClient::global([
        GetUsageRequest::class => MockResponse::make(['message' => 'Invalid API token'], 401),
    ]);

    $this->artisan('usage', ['--no-interaction' => true])->assertFailed();
});

it('writes the error as JSON to stderr and nothing to stdout when running headless', function (array $flags) {
    MockClient::global([
        GetUsageRequest::class => MockResponse::make(['message' => 'Invalid API token'], 401),
    ]);

    $result = runCapturingOutput('usage', $flags);

    expect($result['exitCode'])->toBe(1)
        ->and($result['stdout'])->toBe('')
        ->and(json_decode($result['stderr'], true, flags: JSON_THROW_ON_ERROR))->toBe([
            'error' => true,
            'message' => 'Laravel Cloud rejected your API token (Invalid API token). Run `cloud auth` to sign in again, or `cloud auth:token --add --token=<token>` to save a new token.',
        ]);
})->with([
    '--json' => [['--json' => true]],
    '--no-interaction' => [['--no-interaction' => true]],
]);

it('fails cleanly when the 401 body is not JSON', function () {
    MockClient::global([
        GetUsageRequest::class => MockResponse::make('<!DOCTYPE html><html><body>Unauthorized</body></html>', 401),
    ]);

    $this->artisan('usage', ['--no-interaction' => true])->assertFailed();
});

it('falls back to a generic reason when the 401 body has no usable message', function (array|string $body) {
    expect(unauthorizedMessage(unauthorizedException(body: $body)))
        ->toContain('(Unauthorized)')
        ->toContain('cloud auth');
})->with([
    'html' => '<!DOCTYPE html><html><body>Unauthorized</body></html>',
    'empty' => '',
    'non-string message' => [['message' => ['nested' => 'value']]],
]);

it('tells the user to sign in again when a stored token is rejected', function () {
    expect(unauthorizedMessage(unauthorizedException()))
        ->toContain('Invalid API token')
        ->toContain('cloud auth');
});

it('points at the environment variable when that token is rejected', function () {
    config()->set('cloud.api_token', 'revoked-env-token');

    expect(unauthorizedMessage(unauthorizedException('revoked-env-token')))
        ->toContain('Invalid API token')
        ->toContain('LARAVEL_CLOUD_TOKEN');
});

it('blames the stored token, not the environment variable, when only the stored token is rejected', function () {
    config()->set('cloud.api_token', 'valid-env-token');

    expect(unauthorizedMessage(unauthorizedException('revoked-token')))
        ->toContain('cloud auth')
        ->not->toContain('LARAVEL_CLOUD_TOKEN');
});
