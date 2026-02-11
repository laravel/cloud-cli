<?php

namespace App\Commands;

use App\Client\Requests\AddEnvironmentVariablesRequestData;
use App\Dto\Environment;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
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

        $this->loopUntilValid(fn () => $this->addVariables($environment));

        $this->outputJsonIfWanted('Variables added');

        success('Environment variables added');
    }

    protected function addVariables(Environment $environment): void
    {
        $this->form()->prompt(
            'method',
            fn ($resolver) => $resolver->fromInput(fn ($value) => selectWithContext(
                label: 'Action',
                options: [
                    'append' => ['Append', 'Add without checking for duplicates'],
                    'set' => ['Set', 'Check for duplicates and update existing variables'],
                ],
                default: $value ?? 'append',
            )),
        );

        $variables = $this->isInteractive() ? $this->collectVariables() : $this->collectVariablesFromOptions();

        spin(
            fn () => $this->client->environments()->addVariables(
                new AddEnvironmentVariablesRequestData(
                    environmentId: $environment->id,
                    variables: $variables,
                    method: $this->option('method') ?? 'append',
                ),
            ),
            'Adding variables...',
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

    protected function collectVariables(): array
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

            $this->form()->prompt(
                'variables.'.$counter.'.value',
                fn ($resolver) => $resolver->fromInput(fn ($value) => text(
                    label: 'Value',
                    required: true,
                    default: $value ?? '',
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
