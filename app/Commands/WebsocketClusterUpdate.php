<?php

namespace App\Commands;

use App\Client\Requests\UpdateWebSocketClusterRequestData;
use App\Dto\WebsocketCluster;
use App\Enums\WebsocketServerMaxConnection;
use App\Exceptions\CommandExitException;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class WebsocketClusterUpdate extends BaseCommand
{
    protected ?string $jsonDataClass = WebsocketCluster::class;

    protected $signature = 'websocket-cluster:update
                            {cluster? : The cluster ID or name}
                            {--name= : Cluster name}
                            {--force : Force update without confirmation}';

    protected $description = 'Update a WebSocket cluster';

    protected $aliases = ['ws-cluster:update'];

    public function handle()
    {
        $this->ensureClient();

        intro('Updating WebSocket Cluster');

        $cluster = $this->resolvers()->websocketCluster()->from($this->argument('cluster'));

        $this->defineFields($cluster);

        foreach ($this->form()->filled() as $value) {
            $this->reportChange(
                $value->label(),
                $value->previousValue(),
                $value->value(),
            );
        }

        $updatedCluster = $this->runUpdate(
            fn () => $this->updateCluster($cluster),
            fn () => $this->collectDataAndUpdate($cluster),
        );

        $this->outputJsonIfWanted($updatedCluster);

        success("WebSocket cluster updated: {$updatedCluster->name}");
    }

    protected function updateCluster(WebsocketCluster $cluster): WebsocketCluster
    {
        spin(
            fn () => $this->client->websocketClusters()->update(
                new UpdateWebSocketClusterRequestData(
                    clusterId: $cluster->id,
                    name: $this->form()->get('name'),
                    maxConnections: $this->form()->integer('max_connections'),
                ),
            ),
            'Updating WebSocket cluster...',
        );

        return $this->client->websocketClusters()->get($cluster->id);
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

        $this->form()->define(
            'max_connections',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => select(
                    label: 'Max connections',
                    options: collect(WebsocketServerMaxConnection::cases())->mapWithKeys(fn ($case) => [$case->value => $case->value])->toArray(),
                    default: $value ?? $cluster->maxConnections->value,
                ),
            ),
        )->setPreviousValue($cluster->maxConnections->value);
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
