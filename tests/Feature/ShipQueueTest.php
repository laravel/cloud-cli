<?php

use App\Client\Connector;
use App\Client\Resources\BackgroundProcesses\CreateBackgroundProcessRequest;
use App\Client\Resources\Instances\CreateInstanceRequest;
use App\Client\Resources\Instances\ListInstanceSizesRequest;
use App\Commands\Ship;
use App\Dto\Environment;
use App\Git;
use Illuminate\Support\Facades\File;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

beforeEach(function () {
    $this->repo = sys_get_temp_dir().'/ship-queue-'.uniqid();
    File::ensureDirectoryExists($this->repo.'/project/app');
    File::put($this->repo.'/project/composer.json', json_encode(['require' => []]));
});

afterEach(function () {
    MockClient::destroyGlobal();
    File::deleteDirectory($this->repo);
});

function shipQueueCommand(string $repo, array $options = []): Ship
{
    $command = app(Ship::class);

    $definition = $command->getDefinition();
    $definition->addOption(new InputOption('no-interaction', 'n', InputOption::VALUE_NONE));

    $input = new ArrayInput([...$options, '--root-directory' => 'project', '--no-interaction' => true], $definition);
    $input->setInteractive(false);

    $git = Mockery::mock(Git::class);
    $git->shouldReceive('getRoot')->andReturn($repo);

    (function () use ($input, $git) {
        $this->input = $input;
        $this->output = new BufferedOutput;
        $this->client = new Connector('test-api-token');
        $this->git = $git;
    })->call($command);

    return $command;
}

function shipQueueEnvironment(): Environment
{
    return Environment::createFromResponse(['data' => createEnvironmentResponse([
        'relationships' => ['instances' => ['data' => [['id' => 'inst-1', 'type' => 'instances']]]],
    ])]);
}

function requireComposerPackages(string $repo, array $packages): void
{
    File::put($repo.'/project/composer.json', json_encode([
        'require' => array_fill_keys($packages, '*'),
    ]));
}

it('detects queued jobs only in the app directory', function () {
    $command = shipQueueCommand($this->repo);
    $detects = fn () => (fn () => $this->appDispatchesQueuedJobs())->call($command);

    File::ensureDirectoryExists($this->repo.'/project/vendor/laravel');
    File::put($this->repo.'/project/vendor/laravel/Job.php', '<?php class Job implements ShouldQueue {}');

    expect($detects())->toBeFalse();

    File::ensureDirectoryExists($this->repo.'/project/app/Jobs');
    File::put($this->repo.'/project/app/Jobs/SendEmail.php', '<?php class SendEmail implements ShouldQueue {}');

    expect($detects())->toBeTrue();
});

it('recommends a queue type from the app setup', function (array $packages, ?string $env, string $expected) {
    requireComposerPackages($this->repo, $packages);

    if ($env !== null) {
        File::put($this->repo.'/project/.env.example', "QUEUE_CONNECTION={$env}\n");
    }

    $command = shipQueueCommand($this->repo);

    expect((fn () => $this->recommendedQueueType())->call($command))->toBe($expected);
})->with([
    'database driver' => [[], 'database', 'worker'],
    'no env file' => [[], null, 'worker'],
    'sqs driver' => [[], 'sqs', 'managed'],
    'aws sdk installed' => [['aws/aws-sdk-php'], 'database', 'managed'],
    'horizon wins' => [['laravel/horizon', 'aws/aws-sdk-php'], 'sqs', 'horizon'],
]);

it('rejects an unknown queue option before anything is created', function () {
    $command = shipQueueCommand($this->repo, ['--queue' => 'rabbit']);

    (fn () => $this->resolveRequestedQueueType())->call($command);
})->throws(RuntimeException::class, 'Invalid --queue value `rabbit`');

it('creates a database worker on the app instance', function () {
    $sent = new stdClass;

    MockClient::global([
        CreateBackgroundProcessRequest::class => function (PendingRequest $request) use ($sent) {
            $sent->url = $request->getUrl();
            $sent->body = $request->body()->all();

            return MockResponse::make(['data' => ['id' => 'bp-1', 'type' => 'background_processes', 'attributes' => ['type' => 'worker']]], 201);
        },
    ]);

    $command = shipQueueCommand($this->repo);

    (fn () => $this->provisionQueue('worker', shipQueueEnvironment()))->call($command);

    expect($sent->url)->toContain('inst-1')
        ->and($sent->body['type'])->toBe('worker')
        ->and($sent->body['config']['connection'])->toBe('database')
        ->and($sent->body['config']['queue'])->toBe('default');
});

it('runs horizon as a custom process', function () {
    $sent = new stdClass;

    MockClient::global([
        CreateBackgroundProcessRequest::class => function (PendingRequest $request) use ($sent) {
            $sent->body = $request->body()->all();

            return MockResponse::make(['data' => ['id' => 'bp-1', 'type' => 'background_processes', 'attributes' => ['type' => 'custom']]], 201);
        },
    ]);

    $command = shipQueueCommand($this->repo);

    (fn () => $this->provisionQueue('horizon', shipQueueEnvironment()))->call($command);

    expect($sent->body)->toMatchArray(['type' => 'custom', 'command' => 'php artisan horizon']);
});

it('creates a managed queue on the first current-generation size', function () {
    $sent = new stdClass;

    MockClient::global([
        ListInstanceSizesRequest::class => MockResponse::make([
            'data' => ['managed_queue' => [managedQueueSize('mq-pro-1gb'), managedQueueSize('mq.pro.1gb')]],
        ], 200),
        CreateInstanceRequest::class => function (PendingRequest $request) use ($sent) {
            $sent->body = $request->body()->all();

            return MockResponse::make(['data' => [
                'id' => 'inst-2',
                'type' => 'instances',
                'attributes' => ['name' => 'default', 'type' => 'managed_queue', 'size' => 'mq.pro.1gb', 'scaling_type' => 'custom', 'min_replicas' => 1, 'max_replicas' => 3, 'uses_scheduler' => false],
            ]], 201);
        },
    ]);

    $command = shipQueueCommand($this->repo);

    (fn () => $this->provisionQueue('managed', shipQueueEnvironment()))->call($command);

    expect($sent->body)->toMatchArray([
        'type' => 'managed_queue',
        'size' => 'mq.pro.1gb',
        'name' => 'default',
    ])
        ->and($sent->body)->not->toHaveKey('polling_interval')
        ->and($sent->body['background_processes'][0]['config']['connection'])->toBe('cloud');
});
