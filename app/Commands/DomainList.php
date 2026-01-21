<?php

namespace App\Commands;

use App\Concerns\HasAClient;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class DomainList extends BaseCommand
{
    use HasAClient;

    protected $signature = 'domain:list {environment : The environment ID} {--json : Output as JSON}';

    protected $description = 'List all domains for an environment';

    public function handle()
    {
        $this->ensureClient();

        $this->intro('Listing domains');

        $domains = spin(
            fn () => $this->client->listDomains($this->argument('environment')),
            'Fetching domains...'
        );

        if ($this->option('json')) {
            $this->line(json_encode([
                'data' => array_map(fn ($domain) => [
                    'id' => $domain->id,
                    'domain' => $domain->domain,
                    'status' => $domain->status,
                    'is_primary' => $domain->isPrimary,
                    'verification_status' => $domain->verificationStatus,
                    'created_at' => $domain->createdAt?->toIso8601String(),
                ], $domains->data),
                'links' => $domains->links,
            ], JSON_PRETTY_PRINT));

            return;
        }

        if (count($domains->data) === 0) {
            info('No domains found.');

            return;
        }

        table(
            ['ID', 'Domain', 'Status', 'Primary'],
            collect($domains->data)->map(fn ($domain) => [
                $domain->id,
                $domain->domain,
                $domain->status,
                $domain->isPrimary ? 'Yes' : 'No',
            ])->toArray()
        );
    }
}
