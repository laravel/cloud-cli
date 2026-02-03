<?php

namespace App\Commands;

use App\Concerns\HandlesAvatars;
use App\Concerns\Validates;
use App\Dto\Application;
use App\Git;
use App\Support\UpdateFields;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class ApplicationUpdate extends BaseCommand
{
    use HandlesAvatars;
    use Validates;

    protected $signature = 'application:update
                            {application? : The application ID or name}
                            {--name= : Application name}
                            {--slug= : Application slug}
                            {--slack-channel= : Slack channel for notifications}
                            {--repository= : Repository URL}
                            {--avatar= : Avatar URL or full path to a file}
                            {--default-environment= : Default environment ID or name}
                            {--force : Force update without confirmation}
                            {--json : Output as JSON}';

    protected $description = 'Update an application';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Application');

        $application = $this->resolvers()->application()->from($this->argument('application'));

        $fields = $this->getFieldDefinitions($application);

        $data = [];

        foreach ($fields as $optionName => $field) {
            if ($this->option($optionName)) {
                $data[$field['key']] = $this->option($optionName);

                $this->reportChange(
                    $field['label'],
                    $field['current'],
                    $this->option($optionName),
                );
            }
        }

        $updatedApplication = $this->resolveUpdatedApplication($application, $fields, $data);

        $this->outputJsonIfWanted($updatedApplication);

        success('Application updated');

        outro($updatedApplication->url());
    }

    protected function resolveUpdatedApplication(Application $application, array $fields, array $data): Application
    {
        if (! $this->isInteractive()) {
            if (empty($data)) {
                $this->outputErrorOrThrow('No fields to update. Provide at least one option.');

                exit(self::FAILURE);
            }

            return $this->updateApplication($application, $data);
        }

        if (empty($data)) {
            return $this->loopUntilValid(
                fn () => $this->collectDataAndUpdate($fields, $application),
            );
        }

        if (! $this->shouldRunUpdateFromOptions()) {
            error('Update cancelled');

            exit(self::FAILURE);
        }

        return $this->updateApplication($application, $data);
    }

    protected function updateApplication(Application $application, array $data): Application
    {
        spin(
            fn () => $this->client->applications()->update($application->id, $data),
            'Updating application...',
        );

        return $this->client->applications()->get($application->id);
    }

    protected function shouldRunUpdateFromOptions(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        return confirm('Update the application?');
    }

    protected function getFieldDefinitions(Application $application): array
    {
        $fields = new UpdateFields;

        $fields->add('name', fn ($value) => $this->getNewName($value))->currentValue($application->name);
        $fields->add('slug', fn ($value) => $this->getNewSlug($value))->currentValue($application->slug);
        $fields->add('repository', fn ($value) => $this->getNewRepository($value))->currentValue($application->repositoryFullName ?? '');
        $fields->add('avatar', fn ($value) => $this->getNewAvatar());
        $fields->add('default-environment', fn ($value) => $this->getNewDefaultEnvironmentId($application))->currentValue($application->defaultEnvironmentId)->dataKey('default_environment_id');
        $fields->add('slack-channel', fn ($value) => $this->getNewSlackChannel($value))->currentValue($application->slackChannel)->dataKey('slack_channel');

        return $fields->get();
    }

    protected function collectDataAndUpdate(array $fields, Application $application): Application
    {
        $selection = multiselect(
            label: 'What do you want to update?',
            options: collect($fields)->mapWithKeys(fn ($field, $key) => [
                $key => $field['label'],
            ])->toArray(),
        );

        if (empty($selection)) {
            $this->outputErrorOrThrow('No fields to update. Select at least one option.');

            exit(self::FAILURE);
        }

        foreach ($selection as $optionName) {
            $field = $fields[$optionName];

            $this->addParam($field['key'], fn ($resolver) => $resolver->fromInput(
                fn ($value) => ($field['prompt'])($value ?? $field['current']),
            ));
        }

        return $this->updateApplication($application, $this->getParams());
    }

    protected function getNewName(string $oldName): string
    {
        return text(
            label: 'Name',
            required: true,
            default: $oldName,
            validate: fn ($value) => match (true) {
                strlen($value) < 3 => 'Name must be at least 3 characters',
                strlen($value) > 40 => 'Name must be less than 40 characters',
                ! preg_match('/^[\p{Latin}0-9 _.\'-]+$/u', $value) => 'Name must contain only letters, numbers, spaces, and: _ . \' -',
                default => null,
            },
        );
    }

    protected function getNewSlug(string $oldSlug): string
    {
        return text(
            label: 'Slug',
            required: true,
            default: $oldSlug,
            validate: fn ($value) => match (true) {
                strlen($value) < 3 => 'Slug must be at least 3 characters',
                default => null,
            },
        );
    }

    protected function getNewRepository(string $oldRepository): string
    {
        return text(
            label: 'Repository',
            required: true,
            default: $oldRepository,
        );
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
                $extension = pathinfo($selected, PATHINFO_EXTENSION);

                if (in_array($extension, ['png', 'jpg', 'jpeg', 'webp'])) {
                    return [file_get_contents($selected), $extension];
                }

                $imagick = new \Imagick;
                $imagick->readImage($selected);
                $imagick->setImageFormat('png');

                return [$imagick->getImageBlob(), 'png'];
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

    protected function getNewDefaultEnvironmentId(Application $application): string
    {
        $options = collect($application->environments)
            ->mapWithKeys(fn ($environment) => [
                $environment->id => $environment->name,
            ]);

        return select(
            label: 'Default environment',
            options: $options,
            required: true,
            default: $application->defaultEnvironmentId,
        );
    }

    protected function getNewSlackChannel(string $oldSlackChannel): string
    {
        return text(
            label: 'Slack channel',
            required: true,
            default: $oldSlackChannel,
        );
    }
}
