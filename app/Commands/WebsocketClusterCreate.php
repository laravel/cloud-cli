<?php

namespace App\Commands;

use App\Concerns\DeterminesDefaultRegion;
use App\Concerns\Validates;
use App\Dto\Region;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class WebsocketClusterCreate extends BaseCommand
{
    use DeterminesDefaultRegion;
    use Validates;

    protected $signature = 'websocket-cluster:create
                            {--name= : Cluster name}
                            {--region= : Region}
                            {--json : Output as JSON}';

    protected $description = 'Create a WebSocket cluster';

    public function handle()
    {
        $this->ensureClient();

        intro('Creating WebSocket Cluster');

        $cluster = $this->loopUntilValid($this->createCluster(...));

        $this->outputJsonIfWanted($cluster);

        success('WebSocket cluster created');

        outro("Cluster created: {$cluster->name}");
    }

    protected function createCluster()
    {
        $this->addParam(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => text(
                    label: 'Cluster name',
                    default: $value ?? '',
                    required: true,
                ),
            ),
        );

        $regions = spin(
            fn () => $this->client->meta()->regions(),
            'Fetching regions...',
        );

        $this->addParam(
            'region',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => select(
                    label: 'Region',
                    options: collect($regions)->mapWithKeys(fn (Region $r) => [$r->value => $r->label])->toArray(),
                    default: $value ?? $this->getDefaultRegion(),
                    required: true,
                ))
                ->nonInteractively(fn () => $this->getDefaultRegion()),
        );

        return spin(
            fn () => $this->client->websocketClusters()->create(
                $this->getParam('name'),
                $this->getParam('region'),
                [],
            ),
            'Creating WebSocket cluster...',
        );
    }
}
