<?php

namespace App\Commands;

use Illuminate\Http\Client\RequestException;

use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class InstanceDelete extends BaseCommand
{
    protected $signature = 'instance:delete {instance? : The instance ID} {--force : Skip confirmation}';

    protected $description = 'Delete an instance';

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting Instance');

        $instance = $this->resolvers()->instance()->from($this->argument('instance'));

        $this->confirmDestructive("Delete instance '{$instance->name}'?");

        try {
            spin(
                fn () => $this->client->instances()->delete($instance->id),
                'Deleting instance...',
            );

            $this->outputJsonIfWanted('Instance deleted.');

            success('Instance deleted');
        } catch (RequestException $e) {
            error('Failed to delete instance: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
