<?php

namespace App\Commands;

use App\Client\Requests\UpdateApplicationAvatarRequestData;
use App\Client\Requests\UpdateApplicationRequestData;
use App\Concerns\HandlesAvatars;
use App\Dto\Application;
use App\Exceptions\CommandExitException;
use App\Git;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class ApplicationUpdate extends BaseCommand
{
    protected ?string $jsonDataClass = Application::class;

    use HandlesAvatars;

    protected $signature = 'application:update
                            {application? : The application ID or name}
                            {--name= : Application name}
                            {--slug= : Application slug}
                            {--slack-channel= : Slack channel for notifications}
                            {--repository= : Repository URL}
                            {--avatar= : Avatar URL or full path to a file}
                            {--default-environment= : Default environment ID or name}
                            {--force : Force update without confirmation}';

    protected $description = 'Update an application';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Application');

        $application = $this->resolvers()->application()->from($this->argument('application'));

        $this->defineFields($application);

        foreach ($this->form()->filled() as $value) {
            $this->reportChange(
                $value->label(),
                $value->previousValue(),
                $value->value(),
            );
        }

        $updatedApplication = $this->runUpdate(
            fn () => $this->updateApplication($application),
            fn () => $this->collectDataAndUpdate($application),
        );

        $this->outputJsonIfWanted($updatedApplication);

        success($updatedApplication->url());
    }

    protected function updateApplication(Application $application): Application
    {
        spin(
            fn () => $this->client->applications()->update(
                new UpdateApplicationRequestData(
                    applicationId: $application->id,
                    name: $this->form()->get('name'),
                    slug: $this->form()->get('slug'),
                    defaultEnvironmentId: $this->form()->get('default_environment_id'),
                    repository: $this->form()->get('repository'),
                    slackChannel: $this->form()->get('slack_channel'),
                ),
            ),
            'Updating application...',
        );

        if ($this->form()->get('avatar')) {
            spin(
                fn () => $this->client->applications()->updateAvatar(
                    new UpdateApplicationAvatarRequestData(
                        applicationId: $application->id,
                        avatar: $this->getAvatarFromPath($this->form()->get('avatar')),
                    ),
                ),
                'Updating application avatar...',
            );
        }

        return $this->client->applications()->get($application->id);
    }

    protected function defineFields(Application $application): void
    {
        $this->form()->define(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Name',
                    required: true,
                    default: $value ?? $application->name,
                    validate: fn ($value) => match (true) {
                        strlen($value) < 3 => 'Name must be at least 3 characters',
                        strlen($value) > 40 => 'Name must be less than 40 characters',
                        ! preg_match('/^[\p{Latin}0-9 _.\'-]+$/u', $value) => 'Name must contain only letters, numbers, spaces, and: _ . \' -',
                        default => null,
                    },
                ),
            ),
        );

        $this->form()->define(
            'slug',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Slug',
                    required: true,
                    default: $value ?? $application->slug,
                    validate: fn ($value) => match (true) {
                        strlen($value) < 3 => 'Slug must be at least 3 characters',
                        default => null,
                    },
                ),
            )->setPreviousValue($application->slug),
        );

        $this->form()->define(
            'repository',
            fn ($resolver) => $resolver->fromInput(fn ($value) => text(
                label: 'Repository',
                required: true,
                default: $value ?? $application->repositoryFullName ?? '',
            ))->setPreviousValue($application->repositoryFullName),
        );

        $this->form()->define(
            'avatar',
            fn ($resolver) => $resolver->fromInput($this->getNewAvatar(...)),
        );

        $this->form()->define(
            'default_environment_id',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => $this->getNewDefaultEnvironmentId($application, $value),
            ),
            'default-environment',
        )->setPreviousValue($application->defaultEnvironmentId);

        $this->form()->define(
            'slack_channel',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => $value ?? $application->slackChannel,
            ),
        )->setPreviousValue($application->slackChannel);
    }

    protected function collectDataAndUpdate(Application $application): Application
    {
        $selection = multiselect(
            label: 'What do you want to update?',
            options: collect($this->form()->defined())->mapWithKeys(fn ($field, $key) => [
                $field->key => $field->label(),
            ])->toArray(),
        );

        if (empty($selection)) {
            $this->outputErrorOrThrow('No fields to update. Select at least one option.');

            throw new CommandExitException(self::FAILURE);
        }

        foreach ($selection as $optionName) {
            $this->form()->prompt($optionName);
        }

        return $this->updateApplication($application);
    }

    protected function getNewAvatar(): array
    {
        $avatarCandidates = $this->getAvatarCandidatesFromRepo();

        if ($avatarCandidates->isNotEmpty()) {
            $root = app(Git::class)->getRoot();

            $options = $avatarCandidates->mapWithKeys(fn ($path) => [
                $path => str($path)->after($root)->ltrim(DIRECTORY_SEPARATOR)->toString(),
            ]);

            $options->offsetSet('custom', 'Custom');

            $selected = select(
                label: 'Avatar',
                options: $options,
            );

            if ($selected !== 'custom') {
                return $this->getAvatarFromPath($selected);
            }
        }

        $path = text(
            label: 'Avatar',
            required: true,
            hint: 'Path or URL to the avatar image',
            validate: fn ($value) => match (true) {
                ! file_exists($value) && ! filter_var($value, FILTER_VALIDATE_URL) => 'Invalid path or URL',
                default => null,
            },
        );

        return [
            file_get_contents($path),
            pathinfo($path, PATHINFO_EXTENSION),
        ];
    }

    protected function getNewDefaultEnvironmentId(Application $application, $value = null): string
    {
        $options = collect($application->environments)
            ->mapWithKeys(fn ($environment) => [
                $environment->id => $environment->name,
            ]);

        return select(
            label: 'Default environment',
            options: $options,
            required: true,
            default: $value ?? $application->defaultEnvironmentId,
        );
    }
}
