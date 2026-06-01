<?php

namespace App\Commands;

use App\Dto\EnvironmentInstance;
use App\Enums\InstanceType;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class ManagedQueueSetDefault extends BaseCommand
{
    protected ?string $jsonDataClass = EnvironmentInstance::class;

    protected $signature = 'managed-queue:set-default {instance? : The instance ID}';

    protected $description = 'Set a managed queue as the default';

    protected $aliases = ['queue:set-default'];

    public function handle()
    {
        $this->ensureClient();

        intro('Setting Default Managed Queue');

        $instance = $this->resolvers()->instance()->ofType(InstanceType::MANAGED_QUEUE)->from($this->argument('instance'));

        $updated = spin(
            fn () => $this->client->instances()->setDefault($instance->id),
            'Setting default managed queue...',
        );

        $this->outputJsonIfWanted($updated);

        success("Managed queue '{$instance->name}' set as default");
    }
}
