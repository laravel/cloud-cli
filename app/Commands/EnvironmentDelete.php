<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use Illuminate\Http\Client\RequestException;
use Laravel\Prompts\Concerns\Colors;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\success;

class EnvironmentDelete extends Command
{
    use Colors;
    use HasAClient;

    protected $signature = 'environment:delete
                            {environment : The environment ID}
                            {--force : Skip confirmation}';

    protected $description = 'Delete an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting environment');

        $environmentId = $this->argument('environment');

        if (! $this->option('force')) {
            $environment = spin(
                fn () => $this->client->getEnvironment($environmentId),
                'Fetching environment...'
            );

            if (! confirm("Delete environment '{$environment->name}'?")) {
                $this->info('Cancelled.');

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
