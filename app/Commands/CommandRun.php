<?php

namespace App\Commands;

use App\Client\Requests\RunCommandRequestData;
use App\Concerns\InteractsWithClipbboard;
use App\Dto\Application;
use App\Dto\Command;
use App\Dto\Environment;
use App\Enums\CommandStatus;
use App\Git;
use App\LocalConfig;
use App\Prompts\MonitorCommand;
use App\Support\ValueResolver;
use Carbon\CarbonInterval;
use Illuminate\Support\Sleep;
use Throwable;

use function Laravel\Prompts\autocomplete;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\select;

class CommandRun extends BaseCommand
{
    protected ?string $jsonDataClass = Command::class;

    use InteractsWithClipbboard;

    protected $signature = 'command:run
                            {environment? : The environment ID}
                            {--cmd= : The command to run}
                            {--history : Select a command from recent history}
                            {--no-monitor : Do not monitor the command in real-time}
                            {--copy-output : Copy the output to the clipboard}';

    protected $description = 'Run a command on an environment';

    protected $aliases = ['cmd:run'];

    public function handle()
    {
        $this->ensureClient();

        intro('Running Command');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));
        $command = $this->loopUntilValid(fn () => $this->runCommandOnEnvironment($environment));

        if ($this->option('no-monitor')) {
            $this->outputJsonIfWanted($command);

            return self::SUCCESS;
        }

        if (! $this->isInteractive()) {
            $command = $this->monitorNonInteractively($command);

            $this->outputJsonIfWanted($command);

            if ($this->option('copy-output')) {
                $this->copyToClipboard($command->output ?? '');
            }

            return $command->status === CommandStatus::SUCCESS ? self::SUCCESS : self::FAILURE;
        }

        $this->outputJsonIfWanted($command);

        (new MonitorCommand(
            fn (string $id) => $this->client->commands()->get($id),
            $command,
        ))->display();

        if ($this->option('copy-output')) {
            $command = $this->client->commands()->get($command->id);
            $this->copyToClipboard($command->output ?? '');
            success('Output copied to clipboard');
        }
    }

    protected function monitorNonInteractively(Command $command): Command
    {
        $checkInterval = 3;
        $lastStatus = '';

        while (true) {
            $command = $this->client->commands()->get($command->id);

            $currentStatus = $command->status->label();

            if ($currentStatus !== $lastStatus) {
                $this->writeJsonIfWanted([
                    'command_id' => $command->id,
                    'status' => $command->status->value,
                    'message' => $currentStatus,
                ]);
                $lastStatus = $currentStatus;
            }

            if ($command->isFinished()) {
                return $command;
            }

            Sleep::for(CarbonInterval::seconds($checkInterval));
        }
    }

    protected function runCommandOnEnvironment(Environment $environment): Command
    {
        $artisanCommands = $this->localArtisanCommands($environment->application);

        $this->form()->prompt(
            'command',
            fn (ValueResolver $resolver) => $resolver->fromInput(
                fn ($value) => autocomplete(
                    label: 'Command',
                    default: $this->resolveDefaultCommand($value, $environment->id),
                    options: ! $artisanCommands ? [] : fn (string $value) => $this->getCommandSuggestions($value, $artisanCommands),
                    required: true,
                ),
            ),
            'cmd',
        );

        return dynamicSpinner(
            fn () => $this->client->commands()->run(
                new RunCommandRequestData(
                    environmentId: $environment->id,
                    command: $this->form()->get('command'),
                ),
            ),
            'Running command...',
        );
    }

    protected function getCommandSuggestions(string $value, array $artisanCommands): array
    {
        if (! str_starts_with($value, 'php artisan ')) {
            return [];
        }

        $rest = substr($value, strlen('php artisan '));
        $spacePos = strpos($rest, ' ');

        // Still completing the command name
        if ($spacePos === false) {
            return collect(array_keys($artisanCommands))
                ->filter(fn (string $cmd) => str_starts_with($cmd, $rest))
                ->map(fn (string $cmd) => 'php artisan '.$cmd)
                ->values()
                ->all();
        }

        // Completing options for a known command
        $commandName = substr($rest, 0, $spacePos);

        if (! isset($artisanCommands[$commandName])) {
            return [];
        }

        $tokens = explode(' ', $rest);
        $lastToken = end($tokens);
        $prefix = 'php artisan '.implode(' ', array_slice($tokens, 0, -1)).' ';

        return collect($artisanCommands[$commandName])
            ->filter(fn (string $opt) => str_starts_with($opt, $lastToken))
            ->map(fn (string $opt) => $prefix.$opt)
            ->values()
            ->all();
    }

    protected function resolveDefaultCommand(?string $value, string $environmentId): string
    {
        if ($value) {
            return $value;
        }

        $default = 'php artisan ';

        if ($this->option('history')) {
            return $this->selectFromHistory($environmentId) ?? $default;
        }

        return $default;
    }

    protected function localRepoMatchesApplication(?Application $application): bool
    {
        if (! $application?->repositoryFullName) {
            return false;
        }

        // Fast path: local config already stores which app this repo maps to
        if (app(LocalConfig::class)->applicationId() === $application->id) {
            return true;
        }

        // Fallback: compare the git remote to the app's repository
        try {
            return app(Git::class)->remoteRepo() === $application->repositoryFullName;
        } catch (Throwable) {
            return false;
        }
    }

    protected function localArtisanCommands(?Application $application): ?array
    {
        if (! $this->localRepoMatchesApplication($application) || ! file_exists(getcwd().'/artisan')) {
            return null;
        }

        try {
            $output = shell_exec('php artisan list --format=json 2>/dev/null');

            if (! $output) {
                return null;
            }

            $data = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

            $globalOptions = ['--help', '--quiet', '--verbose', '--version', '--ansi', '--no-ansi', '--no-interaction', '--env'];

            $result = [];

            foreach ($data['commands'] ?? [] as $command) {
                $name = $command['name'] ?? null;

                if (! $name) {
                    continue;
                }

                $options = [];

                foreach ($command['definition']['options'] ?? [] as $option) {
                    $optionName = $option['name'] ?? null;

                    if (! $optionName || in_array($optionName, $globalOptions)) {
                        continue;
                    }

                    $options[] = ($option['accept_value'] ?? false) ? $optionName.'=' : $optionName;
                }

                $result[$name] = $options;
            }

            return $result ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function selectFromHistory(string $environmentId): ?string
    {
        $recentCommands = dynamicSpinner(
            fn () => $this->client->commands()->list($environmentId)
                ->collect()
                ->map(fn ($cmd) => $cmd->command)
                ->unique()
                ->take(10)
                ->values(),
            'Loading command history...',
        );

        if ($recentCommands->isEmpty()) {
            return null;
        }

        return select(
            label: 'Command history',
            options: $recentCommands->toArray(),
            hint: 'You will be able to edit the command in the next step',
        );
    }
}
