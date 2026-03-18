<?php

namespace App\Commands;

use App\Client\Requests\AddEnvironmentVariablesRequestData;
use App\Client\Requests\ReplaceEnvironmentVariablesRequestData;
use Dotenv\Dotenv;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class EnvPush extends BaseCommand
{
    protected $signature = 'env:push
                            {environment? : The environment ID or name}
                            {--file=.env : Path to .env file}
                            {--replace : Replace ALL variables (destructive — removes vars not in file)}
                            {--force : Skip confirmation}
                            {--json : Output as JSON}';

    protected $description = 'Push local .env file contents to an environment (merge by default)';

    public function handle()
    {
        $this->ensureClient();

        $replaceMode = $this->option('replace');

        intro($replaceMode ? 'Replace Environment Variables' : 'Merge Environment Variables');

        $filePath = $this->option('file');

        if (! file_exists($filePath)) {
            $this->failAndExit("File not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        $parsed = Dotenv::parse($content);

        if (empty($parsed)) {
            $this->failAndExit('No variables found in '.$filePath);
        }

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $variables = collect($parsed)
            ->map(fn (string $value, string $key) => ['key' => $key, 'value' => $value])
            ->values()
            ->toArray();

        if ($this->isInteractive() && ! $this->option('force')) {
            $this->displayDiff($environment->environmentVariables, $variables, $replaceMode);

            if ($replaceMode) {
                if (! confirm(
                    label: 'This will replace ALL environment variables. Variables not in the file will be removed. Continue?',
                    yes: 'Yes, replace all variables',
                    no: 'No, cancel',
                )) {
                    $this->failAndExit('Cancelled');
                }
            } else {
                if (! confirm(
                    label: 'Merge these variables into the environment? (existing vars not in file will be kept)',
                    yes: 'Yes, merge variables',
                    no: 'No, cancel',
                )) {
                    $this->failAndExit('Cancelled');
                }
            }
        }

        if (! $this->isInteractive() && ! $this->option('force')) {
            $this->failAndExit('Use --force to skip confirmation in non-interactive mode.');
        }

        if ($replaceMode) {
            spin(
                fn () => $this->client->environments()->replaceVariables(
                    new ReplaceEnvironmentVariablesRequestData(
                        environmentId: $environment->id,
                        variables: $variables,
                    ),
                ),
                'Replacing environment variables...',
            );

            $this->outputJsonIfWanted('Environment variables replaced');

            success('Environment variables replaced ('.count($variables).' variables pushed)');
        } else {
            spin(
                fn () => $this->client->environments()->addVariables(
                    new AddEnvironmentVariablesRequestData(
                        environmentId: $environment->id,
                        variables: $variables,
                        method: 'set',
                    ),
                ),
                'Merging environment variables...',
            );

            $this->outputJsonIfWanted('Environment variables merged');

            success('Environment variables merged ('.count($variables).' variables pushed)');
        }

        return self::SUCCESS;
    }

    protected function displayDiff(array $currentVars, array $newVars, bool $replaceMode = false): void
    {
        $current = collect($currentVars)->keyBy('key');
        $new = collect($newVars)->keyBy('key');

        $added = $new->diffKeys($current);
        $removed = $current->diffKeys($new);
        $changed = $new->filter(fn (array $var) => $current->has($var['key']) && $current->get($var['key'])['value'] !== $var['value']);
        $unchanged = $new->filter(fn (array $var) => $current->has($var['key']) && $current->get($var['key'])['value'] === $var['value']);

        $this->newLine();

        if ($replaceMode) {
            $this->line('<fg=yellow>Mode: REPLACE (destructive — vars not in file will be removed)</>');
        } else {
            $this->line('<fg=cyan>Mode: MERGE (existing vars not in file will be kept)</>');
        }

        $this->newLine();

        if ($added->isNotEmpty()) {
            $this->line('<info>Added ('.count($added).'):</info>');
            $added->each(fn (array $var) => $this->line("  + {$var['key']}"));
        }

        if ($replaceMode && $removed->isNotEmpty()) {
            $this->line('<error>Removed ('.count($removed).'):</error>');
            $removed->each(fn (array $var) => $this->line("  - {$var['key']}"));
        } elseif (! $replaceMode && $removed->isNotEmpty()) {
            $this->line('<fg=gray>Kept ('.count($removed).'): (not in file, will remain unchanged)</>');
            $removed->each(fn (array $var) => $this->line("  = {$var['key']}"));
        }

        if ($changed->isNotEmpty()) {
            $this->line('<comment>Changed ('.count($changed).'):</comment>');
            $changed->each(fn (array $var) => $this->line("  ~ {$var['key']}"));
        }

        if ($unchanged->isNotEmpty()) {
            $this->line('<fg=gray>Unchanged ('.count($unchanged).')</>');
        }

        $this->newLine();
    }
}
