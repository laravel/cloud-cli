<?php

namespace App\Commands;

use App\Dto\EnvironmentInstance;
use App\Enums\InstanceType;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class ManagedQueuePurge extends BaseCommand
{
    protected ?string $jsonDataClass = EnvironmentInstance::class;

    protected $signature = 'managed-queue:purge {instance? : The instance ID} {--force : Skip confirmation}';

    protected $aliases = ['queue:purge'];

    protected $description = 'Purge all messages from a managed queue';

    public function handle()
    {
        $this->ensureClient();

        intro('Purge Managed Queue');

        $instance = $this->resolvers()->instance()->ofType(InstanceType::MANAGED_QUEUE)->from($this->argument('instance'));

        $this->confirmDestructive("Purge all messages from queue '{$instance->name}'?");

        $updated = spin(
            fn () => $this->client->instances()->purge($instance->id),
            'Purging managed queue...',
        );

        $this->outputJsonIfWanted($updated);

        success("Managed queue '{$instance->name}' purged");
    }
}
