<?php

namespace App\Commands;

use App\Client\Requests\UpdateWebSocketClusterRequestData;
use App\Dto\WebsocketCluster;
use App\Exceptions\CommandExitException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class WebsocketClusterUpdate extends BaseCommand
{
    protected $signature = 'websocket-cluster:update
                            {cluster? : The cluster ID or name}
                            {--name= : Cluster name}
                            {--force : Force update without confirmation}
                            {--json : Output as JSON}';

    protected $description = 'Update a WebSocket cluster';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating WebSocket Cluster');

        $cluster = $this->resolvers()->websocketCluster()->from($this->argument('cluster'));

        $this->defineFields($cluster);

        foreach ($this->form()->filled() as $fieldKey => $resolver) {
            $this->reportChange(
                $resolver->label(),
                $resolver->previousValue(),
                $resolver->value(),
            );
        }

        $updatedCluster = $this->resolveUpdatedCluster($cluster);

        $this->outputJsonIfWanted($updatedCluster);

        success('WebSocket cluster updated');

        outro("WebSocket cluster updated: {$updatedCluster->name}");
    }

    protected function resolveUpdatedCluster(WebsocketCluster $cluster): WebsocketCluster
    {
        if (! $this->isInteractive()) {
            if (! $this->form()->hasAnyValues()) {
                $this->outputErrorOrThrow('Provide --name to update.');

                throw new CommandExitException(self::FAILURE);
            }

            return $this->updateCluster($cluster);
        }

        if (! $this->form()->hasAnyValues()) {
            return $this->loopUntilValid(
                fn () => $this->collectDataAndUpdate($cluster),
            );
        }

        if (! $this->shouldRunUpdateFromOptions()) {
            error('Update cancelled');

            throw new CommandExitException(self::FAILURE);
        }

        return $this->updateCluster($cluster);
    }

    protected function updateCluster(WebsocketCluster $cluster): WebsocketCluster
    {
        spin(
            fn () => $this->client->websocketClusters()->update(new UpdateWebSocketClusterRequestData(
                clusterId: $cluster->id,
                name: $this->form()->get('name'),
            )),
            'Updating WebSocket cluster...',
        );

        return $this->client->websocketClusters()->get($cluster->id);
    }

    protected function shouldRunUpdateFromOptions(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        return confirm('Update the WebSocket cluster?');
    }

    protected function defineFields(WebsocketCluster $cluster): void
    {
        $this->form()->define(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Cluster name',
                    required: true,
                    default: $value ?? $cluster->name,
                ),
            ),
        )->setPreviousValue($cluster->name);
    }

    protected function collectDataAndUpdate(WebsocketCluster $cluster): WebsocketCluster
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

        return $this->updateCluster($cluster);
    }
}
