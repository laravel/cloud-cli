<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use Illuminate\Http\Client\RequestException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

class EnvironmentDelete extends BaseCommand
{
    use HasAClient;

    protected $signature = 'environment:delete
                            {environment : The environment ID}
                            {--force : Skip confirmation}';

    protected $description = 'Delete an environment';

    public function handle()
    {
        $this->ensureClient();

        $this->intro('Deleting environment');

        $environmentId = $this->argument('environment');

        if (! $this->option('force')) {
            $environment = spin(
                fn () => $this->client->getEnvironment($environmentId),
                'Fetching environment...'
            );

            if (! confirm("Delete environment '{$environment->name}'?")) {
                info('Cancelled.');

                return;
            }
        }

        try {
            spin(
                fn () => $this->client->deleteEnvironment($environmentId),
                'Deleting environment...'
            );

            success('Environment deleted.');
        } catch (RequestException $e) {
            error('Failed to delete environment: '.$e->getMessage());

            return 1;
        }
    }
}
