<?php

namespace App\Commands;

use App\Dto\EnvironmentInstance;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class ManagedQueuePause extends BaseCommand
{
    protected ?string $jsonDataClass = EnvironmentInstance::class;

    protected $signature = 'managed-queue:pause {instance? : The instance ID}';

    protected $description = 'Pause a managed queue';

    public function handle()
    {
        $this->ensureClient();

        intro('Pause Managed Queue');

        $instance = $this->resolvers()->instance()->from($this->argument('instance'));

        $updated = spin(
            fn () => $this->client->instances()->pause($instance->id),
            'Pausing managed queue...',
        );

        $this->outputJsonIfWanted($updated);

        success("Managed queue '{$instance->name}' paused");
    }
}
