<?php

use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Meta\SubmitFeedbackRequest;
use App\ConfigRepository;
use App\Prompts\Renderer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\PendingCommand;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Tests\Fixtures\InteractiveFeedbackCommand;

beforeEach(function () {
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

/**
 * @return Closure(): stdClass the feedback request that was sent, if any
 */
function setupFeedbackMocks(?MockResponse $response = null): Closure
{
    $sent = new stdClass;

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        SubmitFeedbackRequest::class => function (PendingRequest $request) use ($sent, $response) {
            $sent->url = $request->getUrl();
            $sent->body = $request->body()?->all();

            return $response ?? MockResponse::make([], 200);
        },
    ]);

    return fn () => $sent;
}

it('sends feedback with only a message, defaulting the type to feedback', function () {
    $sent = setupFeedbackMocks();

    $this->artisan('feedback', ['message' => 'The logs were empty.', '--no-interaction' => true])
        ->assertSuccessful();

    expect($sent()->url)->toEndWith('/feedback');
    expect($sent()->body)->toBe(['type' => 'feedback', 'message' => 'The logs were empty.']);
});

it('sends a question about a tool and an environment', function () {
    $sent = setupFeedbackMocks();

    $this->artisan('feedback', [
        'message' => 'How do I rotate a bucket key?',
        '--type' => 'question',
        '--tool' => 'bucket-key:update',
        '--environment' => 'env-1',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent()->body)->toBe([
        'type' => 'question',
        'message' => 'How do I rotate a bucket key?',
        'tool' => 'bucket-key:update',
        'environment_id' => 'env-1',
    ]);
});

it('refuses an unknown type', function () {
    $sent = setupFeedbackMocks();

    $this->artisan('feedback', ['message' => 'Hi', '--type' => 'bug', '--no-interaction' => true])
        ->assertFailed();

    expect($sent()->url ?? null)->toBeNull();
});

it('requires a message when run non-interactively', function () {
    $sent = setupFeedbackMocks();

    $this->artisan('feedback', ['--no-interaction' => true])->assertFailed();

    expect($sent()->url ?? null)->toBeNull();
});

function runInteractiveFeedback(): PendingCommand
{
    Artisan::registerCommand(new InteractiveFeedbackCommand);

    // Accept the default type, then leave the optional command blank.
    Prompt::fake([Key::ENTER, Key::ENTER]);

    return test()->artisan('test:interactive-feedback', ['message' => 'Hi']);
}

it('explains the daily limit and when to try again', function () {
    setupFeedbackMocks(MockResponse::make(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '7260']));

    $suppressed = Renderer::$suppressOutput;

    try {
        runInteractiveFeedback()
            // The error box wraps the message and is one write, which only one expectation can claim.
            ->expectsOutputToContain('Try again in 2 hours 1')
            ->assertFailed();
    } finally {
        Renderer::$suppressOutput = $suppressed;
    }
});

it('says to try again tomorrow when the limit response has no Retry-After', function () {
    setupFeedbackMocks(MockResponse::make(['message' => 'Too Many Attempts.'], 429));

    $suppressed = Renderer::$suppressOutput;

    try {
        runInteractiveFeedback()
            ->expectsOutputToContain('Try again tomorrow.')
            ->assertFailed();
    } finally {
        Renderer::$suppressOutput = $suppressed;
    }
});

it('outputs JSON', function () {
    setupFeedbackMocks();

    Artisan::call('feedback', ['message' => 'Hi', '--json' => true]);

    expect(json_decode(Artisan::output(), true))->toBe(['sent' => true]);
});
