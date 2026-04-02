<?php

namespace App\Commands;

use Illuminate\Http\Client\RequestException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class DomainDelete extends BaseCommand
{
    protected $signature = 'domain:delete {domain? : The domain ID} {--force : Skip confirmation} {--json : Output as JSON}';

    protected $description = 'Delete a domain';

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting Domain');

        $domain = $this->resolvers()->domain()->from($this->argument('domain'));

        if (! $this->option('force') && ! confirm("Delete domain '{$domain->name}'?")) {
            error('Cancelled');

            return self::FAILURE;
        }

        try {
            spin(
                fn () => $this->client->domains()->delete($domain->id),
                'Deleting domain...',
            );

            $this->outputJsonIfWanted('Domain deleted.');

            success('Domain deleted.');
        } catch (RequestException $e) {
            error('Failed to delete domain: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
