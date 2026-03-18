<?php

namespace App\Commands;

use Saloon\Exceptions\Request\RequestException;

use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class EnvironmentDelete extends BaseCommand
{
    protected $signature = 'environment:delete
                            {environment? : The environment ID}
                            {--force : Skip confirmation}';

    protected $description = 'Delete an environment';

    protected $aliases = ['env:delete'];

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting Environment');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $this->confirmDestructive("Delete environment '{$environment->name}'?");

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
