<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use App\Concerns\RequiresRemoteGitRepo;
use App\Concerns\Validates;
use App\Enums\CloudRegion;
use App\Git;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class ApplicationCreate extends BaseCommand
{
    use HasAClient;
    use RequiresRemoteGitRepo;
    use Validates;

    protected $signature = 'application:create
                            {--name= : Application name}
                            {--repository= : Repository (owner/repo format)}
                            {--region= : Application region}
                            {--json : Output as JSON}';

    protected $description = 'Create a new application';

    protected ?string $defaultRegion = null;

    public function handle()
    {
        $this->ensureClient();

        $this->intro('Creating application');

        $application = $this->loopUntilValid($this->createApplication(...));

        $this->outputJsonIfWanted($application);

        $this->outro("Application created: {$application->name}");
    }

    protected function createApplication()
    {
        $git = app(Git::class);

        $this->addParam(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn ($currentValue) => text(
                    label: 'Application name',
                    default: $currentValue ?? basename(getcwd()),
                    required: true,
                ),
            ),
        );

        $this->addParam(
            'repository',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => text(
                    label: 'Repository',
                    required: true,
                    default: $value ?? ($git->hasGitHubRemote() ? $git->remoteRepo() : ''),
                ))
                ->nonInteractively(fn () => $git->hasGitHubRemote() ? $git->remoteRepo() : null),
        );

        $this->addParam(
            'region',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => select(
                    label: 'Region',
                    options: collect(CloudRegion::cases())->mapWithKeys(
                        fn (CloudRegion $region) => [
                            $region->value => $region->label(),
                        ],
                    ),
                    default: $value ?? $this->getDefaultRegion(),
                    required: true,
                ))
                ->nonInteractively(fn () => $this->getDefaultRegion()),
        );

        return spin(
            fn () => $this->client->createApplication(
                $this->getParam('repository'),
                $this->getParam('name'),
                $this->getParam('region'),
            ),
            'Creating application...'
        );
    }

    protected function getDefaultRegion(): ?string
    {
        if ($this->defaultRegion) {
            return $this->defaultRegion;
        }

        $applications = spin(
            fn () => $this->client->listApplications(),
            'Fetching applications...'
        );

        $mostUsedRegion = collect($applications->data)
            ->pluck('region')
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        $this->defaultRegion = CloudRegion::tryFrom($mostUsedRegion ?? '')?->value ?? CloudRegion::US_EAST_2->value;

        return $this->defaultRegion;
    }
}
