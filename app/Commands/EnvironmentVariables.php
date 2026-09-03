<?php

namespace App\Commands;

use App\Client\Requests\AddEnvironmentVariablesRequestData;
use App\Client\Requests\DeleteEnvironmentVariablesRequestData;
use App\Dto\Environment;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class EnvironmentVariables extends BaseCommand
{
    protected $signature = 'environment:variables
                            {environment? : The environment ID or name}
                            {--action= : append, set, or delete}
                            {--key= : Variable key, or a comma-separated list when deleting}
                            {--value= : Variable value}
                            {--force : Force update without confirmation}';

    protected $description = 'Add, update or delete environment variables';

    protected $aliases = ['env:variables', 'env:vars', 'environment:vars'];

    public function handle()
    {
        $this->ensureClient();

        intro('Update Environment Variables');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $this->loopUntilValid(fn () => $this->updateVariables($environment));

        $message = $this->form()->get('action') === 'delete'
            ? 'Environment variables deleted'
            : 'Environment variables updated';

        $this->outputJsonIfWanted($message);

        success($message);
    }

    protected function updateVariables(Environment $environment): void
    {
        $this->form()->prompt(
            'action',
            fn ($resolver) => $resolver->fromInput(fn ($value) => select(
                label: 'Action',
                options: [
                    'append' => 'Append',
                    'set' => 'Set',
                    'delete' => 'Delete',
                ],
                default: $value ?? 'add',
                info: fn ($action) => match ($action) {
                    'append' => 'Add without checking for duplicates',
                    'set' => 'Check for duplicates and update existing variables',
                    'delete' => 'Remove existing variables by key',
                    default => '',
                },
            )),
        );

        if (! in_array($this->form()->get('action'), ['append', 'set', 'delete'])) {
            $this->failAndExit('Invalid action, must be either `append`, `set` or `delete`');
        }

        if ($this->form()->get('action') === 'delete') {
            $this->deleteVariables($environment);

            return;
        }

        $variables = $this->isInteractive()
            ? $this->collectVariables($environment)
            : $this->collectVariablesFromOptions();

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

    protected function deleteVariables(Environment $environment): void
    {
        $keys = $this->isInteractive()
            ? $this->collectKeys($environment)
            : $this->collectKeysFromOptions();

        if ($keys === []) {
            $this->failAndExit('No variable keys given. Pass --key=NAME, or a comma-separated list.');
        }

        $this->confirmDestructive(
            'Delete '.implode(', ', $keys)." from {$environment->name}?",
        );

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

    /**
     * @return list<string>
     */
    protected function collectKeysFromOptions(): array
    {
        return collect(explode(',', (string) $this->option('key')))
            ->map(fn (string $key) => trim($key))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    protected function collectKeys(Environment $environment): array
    {
        $existing = collect($environment->environmentVariables)->pluck('key')->filter();

        if ($existing->isEmpty()) {
            $this->failAndExit('No variables found for this environment.');
        }

        return multiselect(
            label: 'Variables to delete',
            options: $existing->values()->all(),
            required: true,
        );
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
