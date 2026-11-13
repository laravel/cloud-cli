<?php

use App\Client\Connector;
use App\Client\Resources\BackgroundProcesses\CreateBackgroundProcessRequest;
use App\Client\Resources\Instances\UpdateInstanceRequest;
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
    $this->repo = sys_get_temp_dir().'/ship-s2z-'.uniqid();
    File::ensureDirectoryExists($this->repo.'/project');
    File::put($this->repo.'/project/composer.json', json_encode(['require' => []]));

    $this->sentInstance = new stdClass;

    MockClient::global([
        UpdateInstanceRequest::class => function (PendingRequest $request) {
            $this->sentInstance->body = $request->body()->all();

            return MockResponse::make(updatableInstanceResponse(), 200);
        },
        CreateBackgroundProcessRequest::class => MockResponse::make(['data' => ['id' => 'bp-1', 'type' => 'background_processes', 'attributes' => ['type' => 'worker']]], 201),
    ]);
});

afterEach(function () {
    MockClient::destroyGlobal();
    File::deleteDirectory($this->repo);
});

function shipWithoutDatabase(string $repo): Ship
{
    // The database has its own tests, so this keeps the run to the instance update.
    $command = new class extends Ship
    {
        protected function provisionDatabaseOpinionated(): ?string
        {
            return null;
        }
    };

    $definition = $command->getDefinition();
    $definition->addOption(new InputOption('no-interaction', 'n', InputOption::VALUE_NONE));

    $input = new ArrayInput(['--root-directory' => 'project', '--no-interaction' => true], $definition);
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

function shipScaleToZeroEnvironment(): Environment
{
    return Environment::createFromResponse(['data' => createEnvironmentResponse([
        'relationships' => ['instances' => ['data' => [['id' => 'inst-1', 'type' => 'instances']]]],
    ])]);
}

it('leaves the scale to zero Cloud turns on for new environments', function () {
    $command = shipWithoutDatabase($this->repo);

    (fn () => $this->applyOpinionatedOptions(shipScaleToZeroEnvironment()))->call($command);

    expect($this->sentInstance->body)->not->toHaveKey('hibernation_timeout')
        ->and($this->sentInstance->body['uses_scheduler'])->toBeTrue();
});

it('turns scale to zero off when a queue worker runs on the app instance', function (string $queue) {
    $command = shipWithoutDatabase($this->repo);

    (fn () => $this->applyOpinionatedOptions(shipScaleToZeroEnvironment(), $queue))->call($command);

    expect($this->sentInstance->body)->toHaveKey('hibernation_timeout')
        ->and($this->sentInstance->body['hibernation_timeout'])->toBeNull();
})->with(['worker', 'horizon']);
