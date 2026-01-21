<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use App\Concerns\RequiresApplication;
use App\Concerns\Validates;
use App\Dto\Application;
use App\Git;

use function Illuminate\Filesystem\join_paths;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class ApplicationUpdate extends BaseCommand
{
    use HasAClient;
    use RequiresApplication;
    use Validates;

    protected $signature = 'application:update'.
        ' {application? : The application ID or name}'.
        ' {--name= : Application name}'.
        ' {--slack-channel= : Slack channel for notifications}'.
        ' {--repository= : Repository URL}'.
        ' {--avatar= : Avatar URL or full path to a file}'.
        ' {--default-environment= : Default environment ID or name}'.
        ' {--json : Output as JSON}';

    protected $description = 'Update an application';

    public function handle()
    {
        $this->ensureClient();
        $this->intro('Updating application', $this->argument('application'));

        $application = $this->getCloudApplication(showPrompt: false);
        $data = [];

        if ($this->option('name')) {
            $data['name'] = $this->option('name');

            $this->reportChange(
                'Name',
                $application->name,
                $this->option('name'),
            );
        }

        if ($this->option('slack-channel')) {
            $data['slack_channel'] = $this->option('slack-channel');

            $this->reportChange(
                'Slack channel',
                $application->slackChannel ?? 'N/A',
                $this->option('slack-channel'),
            );
        }

        if ($this->option('repository')) {
            $data['repository'] = $this->option('repository');

            $this->reportChange(
                'Repository',
                $application->repositoryFullName ?? 'N/A',
                $this->option('repository'),
            );
        }

        if ($this->option('avatar')) {
            $data['avatar'] = $this->option('avatar');

            $this->reportChange(
                'Avatar',
                'N/A',
                $this->option('avatar'),
            );
        }

        if ($this->option('default-environment')) {
            $data['default_environment_id'] = $this->option('default-environment');

            $this->reportChange(
                'Default environment',
                $application->defaultEnvironmentId ?? 'N/A',
                $this->option('default-environment-id'),
            );
        }

        if ($this->isInteractive()) {
            $data = $this->collectDataFromPrompts($data);
        }

        if (empty($data)) {
            $this->outputErrorOrThrow('No fields to update. Provide at least one option.');

            return self::FAILURE;
        }

        $this->loopUntilValid(
            function ($errors) use ($application, $data) {
                if ($this->isInteractive()) {
                    if ($errors->has('name')) {
                        $data['name'] = $this->getNewName($data['name'] ?? $application->name);
                    }

                    if ($errors->has('slug')) {
                        $data['slug'] = $this->getNewSlug($data['slug'] ?? $application->slug);
                    }

                    if ($errors->has('repository')) {
                        $data['repository'] = $this->getNewRepository($data['repository'] ?? $application->repositoryFullName);
                    }

                    if ($errors->has('avatar')) {
                        $data['avatar'] = $this->getNewAvatar();
                    }

                    if ($errors->has('default_environment_id')) {
                        $data['default_environment_id'] = $this->getNewDefaultEnvironmentId($application);
                    }

                    if ($errors->has('slack_channel')) {
                        $data['slack_channel'] = $this->getNewSlackChannel($data['slack_channel'] ?? $application->slackChannel);
                    }
                } elseif ($errors->hasAny()) {
                    exit(self::FAILURE);
                }

                return spin(fn () => $this->client->updateApplication($application->id, $data), 'Updating application...');
            }
        );

        $application = $this->getCloudApplication(showPrompt: false);

        $this->outputJsonIfWanted($application);

        $this->outro('Application updated');
    }

    protected function collectDataFromPrompts(array $data): array
    {
        do {
            $selection = select(
                label: 'What do you want to update?',
                options: [
                    'name' => 'Name',
                    'slug' => 'Slug',
                    'repository' => 'Repository',
                    'avatar' => 'Avatar',
                    'default_environment_id' => 'Default environment',
                    'slack_channel' => 'Slack channel',
                    'done' => 'Done, update application',
                ],
            );

            if ($selection === 'done') {
                break;
            }

            $data[$selection] = match ($selection) {
                'name' => $this->getNewName($application->name),
                'slug' => $this->getNewSlug($application->slug),
                'repository' => $this->getNewRepository($application->repositoryFullName),
                'avatar' => $this->getNewAvatar(),
                'default_environment_id' => $this->getNewDefaultEnvironmentId($application),
                'slack_channel' => $this->getNewSlackChannel($application->slackChannel ?? ''),
            };
        } while ($selection !== 'done');

        return $data;
    }

    protected function reportChange(string $field, string $oldValue, string $newValue): void
    {
        dataList([
            $field => $this->yellow($oldValue).' '.$this->dim('→').' '.$this->green($newValue),
        ]);
    }

    protected function getNewName(string $oldName): string
    {
        return text(
            label: 'Name',
            required: true,
            default: $oldName,
            validate: fn ($value) => strlen($value) > 3 && strlen($value) < 40 ? null : 'Name must be between 3 and 40 characters',
        );
    }

    protected function getNewSlug(string $oldSlug): string
    {
        return text(
            label: 'Slug',
            required: true,
            default: $oldSlug,
            validate: fn ($value) => strlen($value) > 3 ? null : 'Slug must be at least 3 characters',
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

                /** @phpstan-ignore-next-line */
                $imagick = new \Imagick;
                $imagick->readImage($selected);
                $imagick->setImageFormat('png');

                return [$imagick->getImageBlob(), 'png'];
            }
        }

        $path = text(
            label: 'Avatar',
            required: true,
        );

        return [
            file_get_contents($path),
            pathinfo($selected, PATHINFO_EXTENSION),
        ];
    }

    protected function getAvatarCandidatesFromRepo()
    {
        $root = app(Git::class)->getRoot();

        $possiblePaths = collect([
            'favicon.png',
            'favicon.svg',
            'apple-touch-icon.png',
            'favicon.ico',
            'favicon.jpg',
            'favicon.jpeg',
        ])
            ->map(fn ($path) => join_paths($root, 'public', $path))
            ->filter(fn ($path) => file_exists($path))
            ->values();

        if (class_exists(\Imagick::class)) {
            return $possiblePaths;
        }

        return $possiblePaths
            ->filter(fn ($path) => pathinfo($path, PATHINFO_EXTENSION) === 'png')
            ->values();
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
