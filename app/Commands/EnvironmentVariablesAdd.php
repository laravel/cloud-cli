<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class EnvironmentVariablesAdd extends BaseCommand
{
    protected $signature = 'environment:variables:add
                            {environment? : The environment ID or name}
                            {--key= : Variable key}
                            {--value= : Variable value}
                            {--method= : append or set}
                            {--json : Output as JSON}';

    protected $description = 'Add environment variables';

    public function handle()
    {
        $this->ensureClient();

        intro('Add Environment Variables');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $key = $this->option('key') ?? text(
            label: 'Variable key',
            required: true,
            validate: fn ($v) => preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $v) ? null : 'Key must start with letter or underscore and contain only letters, numbers, underscore',
        );
        $value = $this->option('value') ?? text(
            label: 'Variable value',
            required: true,
        );
        $method = $this->option('method') ?? select(
            label: 'Method',
            options: ['append' => 'Append', 'set' => 'Set (replace if key exists)'],
            default: 'append',
        );

        if (! in_array($method, ['append', 'set'], true)) {
            $this->outputErrorOrThrow('Method must be append or set.');

            exit(self::FAILURE);
        }

        spin(
            fn () => $this->client->environments()->addVariables($environment->id, [$key => $value], $method),
            'Adding variables...',
        );

        success('Environment variables added');
    }
}
