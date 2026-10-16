<?php

namespace App\Commands;

use App\Client\Requests\CreateEnvironmentRequestData;
use App\Dto\Environment;
use App\Git;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class EnvironmentCreate extends BaseCommand
{
    protected ?string $jsonDataClass = Environment::class;

    protected $signature = 'environment:create
                            {application? : The application ID}
                            {--name= : Environment name}
                            {--branch= : Git branch}
                            {--build-command= : Build command to run on deploy}
                            {--cluster= : Dedicated cluster ID to deploy to (see dedicated-cluster:list)}
                            {--edge-network= : Edge network ID or name (see edge-network:list). Default: the organization\'s preferred network}';

    protected $description = 'Create a new environment';

    protected $aliases = ['env:create'];

    public function handle()
    {
        $this->ensureClient();

        intro('Creating Environment');

        $application = $this->resolvers()->application()->from($this->argument('application'));

        $edgeNetwork = $this->resolvers()->edgeNetwork()->from($this->option('edge-network'));

        $environment = $this->loopUntilValid(
            fn () => $this->createEnvironment($application->id, $edgeNetwork?->id),
        );

        $this->outputJsonIfWanted($environment);

        $environment = $this->client->environments()->include('application')->get($environment->id);

        success($environment->url);
    }

    protected function createEnvironment(string $applicationId, ?string $edgeNetworkId)
    {
        $currentBranch = app(Git::class)->currentBranch();

        $this->form()->prompt(
            'name',
            fn ($resolver) => $resolver->fromInput(fn ($value) => text(
                label: 'Name',
                default: $this->form()->get('name') ?? $currentBranch,
                required: true,
            )),
        );

        $this->form()->prompt(
            'branch',
            fn ($resolver) => $resolver->fromInput(fn ($value) => text(
                label: 'Branch',
                default: $this->form()->get('branch') ?? $currentBranch,
                required: true,
            )),
        );

        // Defined but never prompted, so a validation error can name the value we sent.
        $this->form()->define('build_command', fn ($resolver) => $resolver);
        $this->form()->define('cluster_id', fn ($resolver) => $resolver, 'cluster');

        return spin(
            fn () => $this->client->environments()->create(
                new CreateEnvironmentRequestData(
                    applicationId: $applicationId,
                    name: $this->form()->get('name'),
                    branch: $this->form()->get('branch'),
                    buildCommand: $this->form()->get('build_command') ?: null,
                    clusterId: $this->form()->get('cluster_id') ?: null,
                    edgeNetworkId: $edgeNetworkId,
                ),
            ),
            'Creating environment...',
        );
    }
}
