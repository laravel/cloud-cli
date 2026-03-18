<?php

namespace App\Commands;

use Illuminate\Http\Client\RequestException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class EnvironmentStart extends BaseCommand
{
    protected $signature = 'environment:start
                            {environment? : The environment ID or name}
                            {--force : Skip confirmation}';

    protected $description = 'Start an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Starting Environment');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        if (! $this->option('force') && ! confirm("Start environment '{$environment->name}'?")) {
            error('Cancelled');

            return self::FAILURE;
        }

        try {
            spin(
                fn () => $this->client->environments()->start($environment->id),
                'Starting environment...',
            );

            success('Environment started.');
        } catch (RequestException $e) {
            error('Failed to start environment: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
