<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class EnvPull extends BaseCommand
{
    protected $signature = 'env:pull
                            {environment? : The environment ID or name}
                            {--output= : Output file path (default: stdout)}
                            {--json : Output as JSON}';

    protected $description = 'Pull environment variables and output as a .env file';

    public function handle()
    {
        $this->ensureClient();

        intro('Pull Environment Variables');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $variables = $environment->environmentVariables;

        if (empty($variables)) {
            $this->outputJsonIfWanted(['message' => 'No environment variables found']);

            $this->line('No environment variables found.');

            return self::SUCCESS;
        }

        $content = collect($variables)
            ->map(fn (array $var) => $var['key'].'='.$this->formatValue($var['value']))
            ->implode(PHP_EOL).PHP_EOL;

        if ($this->option('output')) {
            file_put_contents($this->option('output'), $content);

            $this->outputJsonIfWanted(['message' => 'Environment variables written to '.$this->option('output')]);

            success('Environment variables written to '.$this->option('output'));

            return self::SUCCESS;
        }

        $this->outputJsonIfWanted(
            collect($variables)->mapWithKeys(fn (array $var) => [$var['key'] => $var['value']])->toArray()
        );

        // Output each line individually so test framework can capture it
        collect($variables)
            ->each(fn (array $var) => $this->line($var['key'].'='.$this->formatValue($var['value'])));

        return self::SUCCESS;
    }

    protected function formatValue(string $value): string
    {
        if (str_contains($value, ' ') || str_contains($value, '"') || str_contains($value, '#') || str_contains($value, '$') || $value === '') {
            return '"'.addcslashes($value, '"\\').'"';
        }

        return $value;
    }
}
