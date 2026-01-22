<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use App\Concerns\RequiresRemoteGitRepo;
use App\Concerns\Validates;
use App\Dto\ValidationErrors;
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
                            {--repository= : Repository (owner/repo format)}
                            {--name= : Application name}
                            {--region= : Application region}
                            {--json : Output as JSON}';

    protected $description = 'Create a new application';

    protected ?string $applicationName = null;

    protected ?string $repository = null;

    protected ?string $region = null;

    public function handle()
    {
        $this->ensureClient();

        $this->intro('Creating application');

        $application = $this->loopUntilValid(
            fn (ValidationErrors $errors) => $this->createApplication($errors)
        );

        $this->outputJsonIfWanted($application);

        $this->outro("Application created: {$application->name}");
    }

    protected function createApplication(ValidationErrors $errors)
    {
        $this->applicationName = $this->resolveName($errors->has('name'));
        $this->repository = $this->resolveRepository($errors->has('repository'));
        $this->region = $this->resolveRegion($errors->has('region'));

        return spin(
            fn () => $this->client->createApplication(
                $this->repository,
                $this->applicationName,
                $this->region,
            ),
            'Creating application...'
        );
    }

    protected function resolveName($hasError): ?string
    {
        if ($this->applicationName && ! $hasError) {
            return $this->applicationName;
        }

        $this->applicationName ??= $this->option('name');

        if ($this->isInteractive() && (! $this->applicationName || $hasError)) {
            $git = app(Git::class);
            $defaultName = $git->currentDirectoryName();

            return text(
                label: 'Application name',
                default: $defaultName,
                required: true,
                validate: fn ($value) => match (true) {
                    strlen($value) < 3 => 'Name must be at least 3 characters',
                    strlen($value) > 40 => 'Name must be less than 40 characters',
                    ! preg_match('/^[\p{Latin}0-9 _.\'-]+$/u', $value) => 'Name must contain only letters, numbers, spaces, and: _ . \' -',
                    default => null,
                },
            );
        }

        if ($this->applicationName) {
            return $this->applicationName;
        }

        $this->outputErrorOrThrow('Application name is required. Provide --name option.');

        return null;
    }

    protected function resolveRepository($hasError): ?string
    {
        if ($this->repository && ! $hasError) {
            return $this->repository;
        }

        $this->repository ??= $this->option('repository');

        $git = app(Git::class);

        if (! $this->repository && $git->isRepo()) {
            if ($git->hasGitHubRemote()) {
                if (! $this->isInteractive()) {
                    return $git->remoteRepo();
                }

                return text(
                    label: 'Repository',
                    default: $git->remoteRepo(),
                    required: true,
                );
            }
        }

        if ($this->isInteractive() && (! $this->repository || $hasError)) {
            return text(
                label: 'Repository',
                required: true,
                default: $this->repository,
            );
        }

        if ($this->repository) {
            return $this->repository;
        }

        $this->outputErrorOrThrow('Repository is required. Provide --repository option or ensure you are in a Git repository with a GitHub remote.');

        return null;
    }

    protected function resolveRegion($hasError): ?string
    {
        if ($this->region && ! $hasError) {
            return $this->region;
        }

        $this->region ??= $this->option('region');

        if (! $this->region || $hasError) {
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

            $defaultRegion = CloudRegion::tryFrom($mostUsedRegion ?? '')?->value ?? CloudRegion::US_EAST_2->value;

            if (! $this->isInteractive()) {
                return $defaultRegion;
            }

            return select(
                label: 'Application region',
                options: collect(CloudRegion::cases())->mapWithKeys(
                    fn (CloudRegion $region) => [
                        $region->value => $region->label(),
                    ],
                ),
                default: $defaultRegion,
                required: true,
            );
        }

        if ($this->region) {
            return $this->region;
        }

        $this->outputErrorOrThrow('Application region is required. Provide --region option.');

        return null;
    }
}
