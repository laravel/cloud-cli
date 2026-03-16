<?php

namespace App\Commands;

use App\Client\Requests\ReplaceEnvironmentVariablesRequestData;
use Dotenv\Dotenv;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class EnvPush extends BaseCommand
{
    protected $signature = 'env:push
                            {environment? : The environment ID or name}
                            {--file= : Path to .env file (default: .env)}
                            {--force : Skip confirmation}
                            {--json : Output as JSON}';

    protected $description = 'Push local .env file contents to an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Push Environment Variables');

        $filePath = $this->option('file') ?? '.env';

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
            $this->displayDiff($environment->environmentVariables, $variables);

            if (! confirm(
                label: 'This will replace ALL environment variables. Continue?',
                yes: 'Yes, replace all variables',
                no: 'No, cancel',
            )) {
                $this->failAndExit('Cancelled');
            }
        }

        if (! $this->isInteractive() && ! $this->option('force')) {
            $this->failAndExit('Use --force to skip confirmation in non-interactive mode.');
        }

        spin(
            fn () => $this->client->environments()->replaceVariables(
                new ReplaceEnvironmentVariablesRequestData(
                    environmentId: $environment->id,
                    variables: $variables,
                ),
            ),
            'Pushing environment variables...',
        );

        $this->outputJsonIfWanted('Environment variables replaced');

        success('Environment variables replaced ('.count($variables).' variables pushed)');

        return self::SUCCESS;
    }

    protected function displayDiff(array $currentVars, array $newVars): void
    {
        $current = collect($currentVars)->keyBy('key');
        $new = collect($newVars)->keyBy('key');

        $added = $new->diffKeys($current);
        $removed = $current->diffKeys($new);
        $changed = $new->filter(fn (array $var) => $current->has($var['key']) && $current->get($var['key'])['value'] !== $var['value']);
        $unchanged = $new->filter(fn (array $var) => $current->has($var['key']) && $current->get($var['key'])['value'] === $var['value']);

        $this->newLine();

        if ($added->isNotEmpty()) {
            $this->line('<info>Added ('.count($added).'):</info>');
            $added->each(fn (array $var) => $this->line("  + {$var['key']}"));
        }

        if ($removed->isNotEmpty()) {
            $this->line('<error>Removed ('.count($removed).'):</error>');
            $removed->each(fn (array $var) => $this->line("  - {$var['key']}"));
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
