<?php

namespace App\Commands;

use Illuminate\Http\Client\RequestException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class EnvironmentStop extends BaseCommand
{
    protected $signature = 'environment:stop
                            {environment? : The environment ID or name}
                            {--force : Skip confirmation}';

    protected $description = 'Stop an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Stopping Environment');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        warning('Stopping this environment will take your application offline.');

        if (! $this->option('force') && ! confirm("Stop environment '{$environment->name}'?")) {
            error('Cancelled');

            return self::FAILURE;
        }

        try {
            spin(
                fn () => $this->client->environments()->stop($environment->id),
                'Stopping environment...',
            );

            success('Environment stopped.');
        } catch (RequestException $e) {
            error('Failed to stop environment: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
