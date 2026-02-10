<?php

namespace App\Commands;

use App\Client\Requests\UpdateEnvironmentRequestData;
use App\Dto\Environment;
use App\Exceptions\CommandExitException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class EnvironmentUpdate extends BaseCommand
{
    protected $signature = 'environment:update
                            {environment? : The environment ID or name}
                            {--branch= : Git branch}
                            {--build-command= : Build command}
                            {--deploy-command= : Deploy command}
                            {--force : Force update without confirmation}
                            {--json : Output as JSON}';

    protected $description = 'Update an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Environment');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $this->defineFields($environment);

        foreach ($this->form()->filled() as $key => $value) {
            $this->reportChange(
                $value->label(),
                $value->previousValue(),
                $value->value(),
            );
        }

        $updatedEnvironment = $this->resolveUpdatedEnvironment($environment);

        $this->outputJsonIfWanted($updatedEnvironment);

        success('Environment updated');

        outro("Environment updated: {$updatedEnvironment->name}");
    }

    protected function resolveUpdatedEnvironment(Environment $environment): Environment
    {
        if (! $this->isInteractive()) {
            if (! $this->form()->hasAnyValues()) {
                $this->outputErrorOrThrow('No fields to update. Provide at least one option.');

                throw new CommandExitException(self::FAILURE);
            }

            return $this->updateEnvironment($environment);
        }

        if (! $this->form()->hasAnyValues()) {
            return $this->loopUntilValid(
                fn () => $this->collectDataAndUpdate($environment),
            );
        }

        if (! $this->shouldRunUpdateFromOptions()) {
            error('Update cancelled');

            throw new CommandExitException(self::FAILURE);
        }

        return $this->updateEnvironment($environment);
    }

    protected function updateEnvironment(Environment $environment): Environment
    {
        spin(
            fn () => $this->client->environments()->update(new UpdateEnvironmentRequestData(
                environmentId: $environment->id,
                branch: $this->form()->get('branch'),
                buildCommand: $this->form()->get('build_command'),
                deployCommand: $this->form()->get('deploy_command'),
            )),
            'Updating environment...',
        );

        return $this->client->environments()->get($environment->id);
    }

    protected function shouldRunUpdateFromOptions(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        return confirm('Update the environment?');
    }

    protected function defineFields(Environment $environment): void
    {
        $this->form()->define(
            'branch',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Branch',
                    default: $value ?? $environment->branch ?? '',
                ),
            ),
        )->setPreviousValue($environment->branch ?? '');

        $this->form()->define(
            'build_command',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Build command',
                    default: $value ?? $environment->buildCommand ?? '',
                ),
            ),
            'build-command',
        )->setPreviousValue($environment->buildCommand ?? '');

        $this->form()->define(
            'deploy_command',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Deploy command',
                    default: $value ?? $environment->deployCommand ?? '',
                ),
            ),
            'deploy-command',
        )->setPreviousValue($environment->deployCommand ?? '');
    }

    protected function collectDataAndUpdate(Environment $environment): Environment
    {
        $selection = multiselect(
            label: 'What do you want to update?',
            options: collect($this->form()->defined())->mapWithKeys(fn ($field, $key) => [
                $field->key => $field->label(),
            ])->toArray(),
        );

        if (empty($selection)) {
            $this->outputErrorOrThrow('No fields to update. Select at least one option.');

            throw new CommandExitException(self::FAILURE);
        }

        foreach ($selection as $optionName) {
            $this->form()->prompt($optionName);
        }

        return $this->updateEnvironment($environment);
    }
}
