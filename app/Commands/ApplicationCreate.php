<?php

namespace App\Commands;

use App\Client\Requests\CreateApplicationRequestData;
use App\Concerns\DeterminesDefaultRegion;
use App\Concerns\RequiresRemoteGitRepo;
use App\Dto\Application;
use App\Dto\Region;
use App\Git;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class ApplicationCreate extends BaseCommand
{
    protected ?string $jsonDataClass = Application::class;

    use DeterminesDefaultRegion;
    use RequiresRemoteGitRepo;

    protected $signature = 'application:create
                            {--name= : Application name}
                            {--repository= : Repository (owner/repo format)}
                            {--region= : Application region}';

    protected $description = 'Create a new application';

    protected $aliases = ['app:create'];

    public function handle()
    {
        $this->ensureClient();

        intro('Creating Application');

        $application = $this->loopUntilValid($this->createApplication(...));

        $this->outputJsonIfWanted($application);

        $application = $this->client->applications()->get($application->id);

        success($application->url());
    }

    protected function createApplication()
    {
        $git = app(Git::class);

        $this->form()->prompt(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn ($currentValue) => text(
                    label: 'Name',
                    default: $currentValue ?? basename(getcwd()),
                    required: true,
                ),
            ),
        );

        $this->form()->prompt(
            'repository',
            fn ($resolver) => $resolver
                ->fromInput(
                    fn (?string $value) => text(
                        label: 'Repository',
                        required: true,
                        default: $value ?? ($git->hasGitHubRemote() ? $git->remoteRepo() : ''),
                    ),
                )
                ->nonInteractively(fn () => $git->hasGitHubRemote() ? $git->remoteRepo() : null),
        );

        $regions = spin(
            fn () => $this->client->meta()->regions(),
            'Fetching regions...',
        );

        $this->form()->prompt(
            'region',
            fn ($resolver) => $resolver
                ->fromInput(
                    fn (?string $value) => select(
                        label: 'Region',
                        options: collect($regions)->mapWithKeys(fn (Region $region) => [
                            $region->value => $region->label,
                        ])->toArray(),
                        default: $value ?? $this->getDefaultRegion(),
                        required: true,
                    ),
                )
                ->nonInteractively(fn () => $this->getDefaultRegion()),
        );

        return spin(
            fn () => $this->client->applications()->create(
                new CreateApplicationRequestData(
                    repository: $this->form()->get('repository'),
                    name: $this->form()->get('name'),
                    region: $this->form()->get('region'),
                ),
            ),
            'Creating application...',
        );
    }
}
