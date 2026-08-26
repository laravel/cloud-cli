<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class SecretDelete extends BaseCommand
{
    protected $signature = 'secret:delete
                            {secret? : The secret ID}
                            {--force : Skip confirmation}';

    protected $description = 'Delete a secret';

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting Secret');

        $secret = $this->resolvers()->secret()->from($this->argument('secret'));

        $this->confirmDestructive("Delete secret '{$secret->key}'? It will be detached from every environment using it.");

        spin(
            fn () => $this->client->secrets()->delete($secret->id),
            'Deleting secret...',
        );

        $this->outputJsonIfWanted('Secret deleted.');

        success('Secret deleted');
    }
}
