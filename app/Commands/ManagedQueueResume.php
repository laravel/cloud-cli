<?php

namespace App\Commands;

use App\Dto\EnvironmentInstance;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class ManagedQueueResume extends BaseCommand
{
    protected ?string $jsonDataClass = EnvironmentInstance::class;

    protected $signature = 'managed-queue:resume {instance? : The instance ID}';

    protected $description = 'Resume a managed queue';

    public function handle()
    {
        $this->ensureClient();

        intro('Resume Managed Queue');

        $instance = $this->resolvers()->instance()->from($this->argument('instance'));

        $updated = spin(
            fn () => $this->client->instances()->resume($instance->id),
            'Resuming managed queue...',
        );

        $this->outputJsonIfWanted($updated);

        success("Managed queue '{$instance->name}' resumed");
    }
}
