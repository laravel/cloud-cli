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

class DomainDelete extends Command
{
    use Colors;
    use HasAClient;

    protected $signature = 'domain:delete {domain : The domain ID} {--force : Skip confirmation}';

    protected $description = 'Delete a domain';

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting domain');

        $domainId = $this->argument('domain');

        if (! $this->option('force')) {
            $domain = spin(
                fn () => $this->client->getDomain($domainId),
                'Fetching domain...'
            );

            if (! confirm("Delete domain '{$domain->domain}'?")) {
                $this->info('Cancelled.');

                return;
            }
        }

        try {
            spin(
                fn () => $this->client->deleteDomain($domainId),
                'Deleting domain...'
            );

            success('Domain deleted.');
        } catch (RequestException $e) {
            error('Failed to delete domain: '.$e->getMessage());

            return 1;
        }
    }
}
