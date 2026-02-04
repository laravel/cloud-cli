<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class EnvironmentVariablesReplace extends BaseCommand
{
    protected $signature = 'environment:variables:replace
                            {environment? : The environment ID or name}
                            {--file= : Path to .env file (content used as replacement)}
                            {--json : Output as JSON}';

    protected $description = 'Replace all environment variables with content from a file';

    public function handle()
    {
        $this->ensureClient();

        intro('Replace Environment Variables');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $file = $this->option('file');

        if (! $file) {
            $this->outputErrorOrThrow('Provide --file with path to .env file.');

            exit(self::FAILURE);
        }

        if (! is_readable($file)) {
            $this->outputErrorOrThrow("Cannot read file: {$file}");

            exit(self::FAILURE);
        }

        $content = file_get_contents($file);

        spin(
            fn () => $this->client->environments()->replaceVariables($environment->id, [], $content),
            'Replacing variables...',
        );

        success('Environment variables replaced');
    }
}
