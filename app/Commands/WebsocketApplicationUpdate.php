<?php

namespace App\Commands;

use App\Client\Requests\UpdateWebSocketApplicationRequestData;
use App\Dto\WebsocketApplication;
use App\Dto\WebsocketCluster;
use App\Exceptions\CommandExitException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class WebsocketApplicationUpdate extends BaseCommand
{
    protected $signature = 'websocket-application:update
                            {cluster? : The WebSocket cluster ID or name}
                            {application? : The application ID or name}
                            {--name= : Application name}
                            {--force : Force update without confirmation}
                            {--json : Output as JSON}';

    protected $description = 'Update a WebSocket application';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating WebSocket Application');

        $cluster = $this->resolvers()->websocketCluster()->from($this->argument('cluster'));
        $app = $this->resolvers()->websocketApplication()->from($cluster, $this->argument('application'));

        $this->defineFields($app);

        foreach ($this->form()->filled() as $fieldKey => $resolver) {
            $this->reportChange(
                $resolver->label(),
                $resolver->previousValue(),
                $resolver->value(),
            );
        }

        $updatedApp = $this->resolveUpdatedApplication($cluster, $app);

        $this->outputJsonIfWanted($updatedApp);

        success('WebSocket application updated');

        outro("WebSocket application updated: {$updatedApp->name}");
    }

    protected function resolveUpdatedApplication(WebsocketCluster $cluster, WebsocketApplication $app): WebsocketApplication
    {
        if (! $this->isInteractive()) {
            if (! $this->form()->hasAnyValues()) {
                $this->outputErrorOrThrow('Provide --name to update.');

                throw new CommandExitException(self::FAILURE);
            }

            return $this->updateApplication($cluster, $app);
        }

        if (! $this->form()->hasAnyValues()) {
            return $this->loopUntilValid(
                fn () => $this->collectDataAndUpdate($cluster, $app),
            );
        }

        if (! $this->shouldRunUpdateFromOptions()) {
            error('Update cancelled');

            throw new CommandExitException(self::FAILURE);
        }

        return $this->updateApplication($cluster, $app);
    }

    protected function updateApplication(WebsocketCluster $cluster, WebsocketApplication $app): WebsocketApplication
    {
        spin(
            fn () => $this->client->websocketApplications()->update(new UpdateWebSocketApplicationRequestData(
                clusterId: $cluster->id,
                applicationId: $app->id,
                name: $this->form()->get('name'),
            )),
            'Updating WebSocket application...',
        );

        return $this->client->websocketApplications()->get($cluster->id, $app->id);
    }

    protected function shouldRunUpdateFromOptions(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        return confirm('Update the WebSocket application?');
    }

    protected function defineFields(WebsocketApplication $app): void
    {
        $this->form()->define(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Application name',
                    required: true,
                    default: $value ?? $app->name,
                ),
            ),
        )->setPreviousValue($app->name);
    }

    protected function collectDataAndUpdate(WebsocketCluster $cluster, WebsocketApplication $app): WebsocketApplication
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

        return $this->updateApplication($cluster, $app);
    }
}
