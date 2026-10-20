<?php

namespace App\Commands;

use App\Client\Requests\CreateApplicationRequestData;
use App\Concerns\DeterminesDefaultRegion;
use App\Concerns\RequiresRemoteGitRepo;
use App\Concerns\ResolvesSourceProvider;
use App\Dto\Application;
use App\Dto\Region;
use App\Enums\SourceProvider;
use App\Enums\StarterKit;
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
    use ResolvesSourceProvider;

    protected $signature = 'application:create
                            {--name= : Application name}
                            {--repository= : Repository (owner/repo format)}
                            {--source-provider= : Source provider (github, gitlab, gitlab_self_hosted, bitbucket). Default: detected from the origin remote}
                            {--region= : Application region}
                            {--root-directory= : Repository subdirectory containing the app (monorepos)}
                            {--build-command= : Build command to run on deploy}
                            {--cluster= : Dedicated cluster ID to deploy to (see dedicated-cluster:list)}
                            {--starter-kit= : Create a new private GitHub repository at --repository from a starter kit (laravel, laravel-react, laravel-vue, laravel-livewire, laravel-svelte, nextjs, nuxt, express, go, django, fastapi, rails)}';

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

        $this->form()->define('starter_kit', fn ($resolver) => $resolver);

        $starterKit = $this->starterKitFrom($this->form()->get('starter_kit'));

        // A starter kit creates the repository, so the origin remote is no guide to its name.
        $defaultRepository = $starterKit === null && $git->hasRemote() ? $git->remoteRepo() : null;

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
                        default: $value ?? $defaultRepository ?? '',
                    ),
                )
                ->nonInteractively(fn () => $defaultRepository),
        );

        $sourceProvider = $starterKit === null
            ? $this->resolveSourceProvider()
            : $this->starterKitSourceProvider();

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

        // Defined but never prompted, so a validation error can name the value we sent.
        $this->form()->define('root_directory', fn ($resolver) => $resolver);

        $this->form()->define('build_command', fn ($resolver) => $resolver);
        $this->form()->define('cluster_id', fn ($resolver) => $resolver, 'cluster');

        $rootDirectory = $this->form()->get('root_directory');

        return spin(
            fn () => $this->client->applications()->create(
                new CreateApplicationRequestData(
                    repository: $this->form()->get('repository'),
                    name: $this->form()->get('name'),
                    region: $this->form()->get('region'),
                    rootDirectory: $rootDirectory === '' ? null : $rootDirectory,
                    sourceProvider: $sourceProvider,
                    buildCommand: $this->form()->get('build_command') ?: null,
                    clusterId: $this->form()->get('cluster_id') ?: null,
                    starterKit: $starterKit,
                ),
            ),
            'Creating application...',
        );
    }

    protected function starterKitFrom(?string $value): ?StarterKit
    {
        if ($value === null || $value === '') {
            return null;
        }

        return StarterKit::tryFrom($value) ?? $this->failAndExit(
            'Unknown starter kit ['.$value.']. Use one of: '
            .implode(', ', array_column(StarterKit::cases(), 'value')),
        );
    }

    /**
     * Starter kits only exist on GitHub. A different --source-provider still goes through,
     * so the API's error explains why.
     */
    protected function starterKitSourceProvider(): ?SourceProvider
    {
        $this->defineSourceProviderField();

        return $this->sourceProviderFrom($this->form()->get('source_provider') ?: SourceProvider::GITHUB->value);
    }
}
