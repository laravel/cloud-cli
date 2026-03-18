<?php

namespace App\Commands;

use App\Client\Requests\AddEnvironmentVariablesRequestData;
use App\Client\Requests\DeleteEnvironmentVariablesRequestData;
use App\Client\Requests\ReplaceEnvironmentVariablesRequestData;
use App\Dto\Environment;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class EnvironmentVariables extends BaseCommand
{
    protected $aliases = ['vars'];

    protected $signature = 'environment:variables
                            {environment? : The environment ID or name}
                            {--action= : append, set, replace, or delete}
                            {--key=* : Variable key(s)}
                            {--value= : Variable value}
                            {--force : Force update without confirmation}
                            {--json : Output as JSON}';

    protected $description = 'Replace all environment variables with content from a file';

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
                    'delete' => ['Delete', 'Remove variables by key'],
                ],
                default: $value ?? 'add',
            )),
        );

        if (! in_array($this->form()->get('action'), ['append', 'set', 'replace', 'delete'])) {
            $this->failAndExit('Invalid action, must be either `append`, `set`, `replace` or `delete`');
        }

        if ($this->form()->get('action') === 'delete') {
            $this->deleteVariables($environment);

            return;
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

    protected function deleteVariables(Environment $environment): void
    {
        $keys = $this->option('key');

        if (empty($keys)) {
            if (! $this->isInteractive()) {
                $this->failAndExit('You must provide at least one --key to delete.');
            }

            $keys = [];
            $adding = true;

            while ($adding) {
                $key = text(
                    label: 'Key to delete',
                    required: true,
                );

                $keys[] = $key;

                $adding = confirm(
                    'Delete another variable?',
                    no: 'No, done',
                    yes: 'Yes, delete another',
                    default: false,
                );
            }
        }

        if (! $this->option('force')) {
            if (! $this->isInteractive()) {
                $this->failAndExit('Cancelled. Use --force to force deletion.');
            }

            if (! confirm(
                label: 'Are you sure you want to delete '.count($keys).' variable(s)?',
                yes: 'Yes, delete',
                no: 'No, cancel',
            )) {
                $this->failAndExit('Cancelled');
            }
        }

        spin(
            fn () => $this->client->environments()->deleteVariables(
                new DeleteEnvironmentVariablesRequestData(
                    environmentId: $environment->id,
                    keys: $keys,
                ),
            ),
            'Deleting variables...',
        );
    }

    protected function collectVariablesFromOptions(): array
    {
        $keys = $this->option('key');

        return [
            [
                'key' => $keys[0] ?? null,
                'value' => $this->option('value'),
            ],
        ];
    }

    protected function collectVariables(Environment $environment): array
    {
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

            $existingValue = collect($environment->environmentVariables)->firstWhere(
                'key',
                $this->form()->get('variables.'.$counter.'.key'),
            )['value'] ?? '';

            $this->form()->prompt(
                'variables.'.$counter.'.value',
                fn ($resolver) => $resolver->fromInput(fn ($value) => text(
                    label: 'Value',
                    required: true,
                    default: $value ?? $existingValue,
                )),
            );

            $adding = confirm(
                'Add another variable?',
                no: 'No, done',
                yes: 'Yes, add another',
                default: false,
            );

            $counter++;
        }

        $variables = collect($this->form()->filled())
            ->filter(fn ($field) => str_starts_with($field->key, 'variables.'))
            ->undot()
            ->toArray();

        return collect($variables['variables'])
            ->map(fn ($var) => [
                'key' => $var['key']->value(),
                'value' => $var['value']->value(),
            ])
            ->toArray();
    }
}
