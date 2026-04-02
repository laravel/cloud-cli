<?php

namespace App\Commands;

use Illuminate\Http\Client\RequestException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class EnvironmentDelete extends BaseCommand
{
    protected $signature = 'environment:delete
                            {environment? : The environment ID}
                            {--force : Skip confirmation}
                            {--json : Output as JSON}';

    protected $description = 'Delete an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting Environment');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        if (! $this->option('force') && ! confirm("Delete environment '{$environment->name}'?")) {
            error('Cancelled');

            return self::FAILURE;
        }

        try {
            spin(
                fn () => $this->client->environments()->delete($environment->id),
                'Deleting environment...',
            );

            $this->outputJsonIfWanted('Environment deleted.');

            success('Environment deleted.');
        } catch (RequestException $e) {
            error('Failed to delete environment: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
