<?php

namespace App\Commands;

use App\Client\Requests\AddEnvironmentVariablesRequestData;
use App\Client\Requests\ReplaceEnvironmentVariablesRequestData;
use App\Dto\Environment;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\password;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class EnvironmentVariables extends BaseCommand
{
    protected $signature = 'environment:variables
                            {environment? : The environment ID or name}
                            {--action= : append, set, or replace}
                            {--key= : Variable key}
                            {--value= : Variable value}
                            {--force : Force update without confirmation}';

    protected $description = 'Replace all environment variables with content from a file';

    protected $aliases = ['env:variables', 'env:vars', 'environment:vars'];

    public function handle()
    {
        $this->ensureClient();

        intro('Update Environment Variables');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $this->loopUntilValid(fn () => $this->updateVariables($environment));

        $this->outputJsonIfWanted('Environment variables updated');

        success('Environment variables updated');
    }

    protected function updateVariables(Environment $environment): void
    {
        $this->form()->prompt(
            'action',
            fn ($resolver) => $resolver->fromInput(fn ($value) => selectWithContext(
                label: 'Action',
                options: [
                    'append' => ['Append', 'Add without checking for duplicates'],
                    'set' => ['Set', 'Check for duplicates and update existing variables'],
                    'replace' => ['Replace', 'Replace all existing variable'],
                ],
                default: $value ?? 'add',
            )),
        );

        if (! in_array($this->form()->get('action'), ['append', 'set', 'replace'])) {
            $this->failAndExit('Invalid action, must be either `append`, `set` or `replace`');
        }

        if ($this->form()->get('action') === 'replace' && ! $this->option('force')) {
            if (! $this->isInteractive()) {
                $this->failAndExit('Cancelled. Use --force to force update.');
            }

            if (! confirm(
                label: 'I understand that this will *replace* all existing variables for this environment',
                yes: 'Yes, continue',
                no: 'No, cancel',
            )) {
                $this->failAndExit('Cancelled');
            }
        }

        $variables = $this->isInteractive()
            ? $this->collectVariables($environment)
            : $this->collectVariablesFromOptions();

        if ($this->form()->get('action') === 'replace') {
            spin(
                fn () => $this->client->environments()->replaceVariables(
                    new ReplaceEnvironmentVariablesRequestData(
                        environmentId: $environment->id,
                        variables: $variables,
                    ),
                ),
                'Replacing variables...',
            );
        } else {
            spin(
                fn () => $this->client->environments()->addVariables(
                    new AddEnvironmentVariablesRequestData(
                        environmentId: $environment->id,
                        variables: $variables,
                        method: $this->form()->get('action'),
                    ),
                ),
                $this->form()->get('action') === 'append' ? 'Appending variables...' : 'Setting variables...',
            );
        }
    }

    protected function collectVariablesFromOptions(): array
    {
        return [
            [
                'key' => $this->option('key'),
                'value' => $this->option('value'),
            ],
        ];
    }

    protected function collectVariables(Environment $environment): array
    {
        $reveal = (bool) $this->option('show-sensitive');
        $variables = [];
        $adding = true;
        $counter = 0;

        while ($adding) {
            $this->form()->prompt(
                'variables.'.$counter.'.key',
                fn ($resolver) => $resolver->fromInput(fn ($value) => text(
                    label: 'Key',
                    required: true,
                    default: $value ?? '',
                )),
            );

            $key = $this->form()->get('variables.'.$counter.'.key');

            $existingValue = collect($environment->environmentVariables)->firstWhere(
                'key',
                $key,
            )['value'] ?? '';

            if ($existingValue !== '' && confirm(
                label: "Keep existing value for {$key}?",
                default: true,
            )) {
                $value = $existingValue;
            } else {
                $this->form()->prompt(
                    'variables.'.$counter.'.value',
                    fn ($resolver) => $resolver->fromInput(fn ($value) => $reveal
                        ? text(label: 'Value', required: true, default: $value ?? '')
                        : password(label: 'Value', required: true),
                    ),
                );

                $value = $this->form()->get('variables.'.$counter.'.value');
            }

            $variables[] = ['key' => $key, 'value' => $value];

            $adding = confirm(
                'Add another variable?',
                no: 'No, done',
                yes: 'Yes, add another',
                default: false,
            );

            $counter++;
        }

        return $variables;
    }
}
