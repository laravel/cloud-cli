<?php

namespace App\Commands;

use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class DomainList extends BaseCommand
{
    protected $signature = 'domain:list {environment? : The environment ID} {--json : Output as JSON}';

    protected $description = 'List all domains for an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Domains');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $domains = spin(
            fn () => $this->client->domains()->list($environment->id),
            'Fetching domains...',
        );

        $items = $domains->collect();

        if ($items->isEmpty()) {
            $this->failAndExit('No domains found.');
        }

        $this->outputJsonIfWanted($items);

        dataTable(
            headers: ['ID', 'Name', 'Status', 'Primary'],
            rows: $items->map(fn ($domain) => [
                $domain->id,
                $domain->name,
                $domain->status(),
                $domain->isPrimary() ? 'Yes' : 'No',
            ])->toArray(),
            actions: [
                Key::ENTER => [
                    fn ($row) => $this->call('domain:get', ['domain' => $row[0]]),
                    'View',
                ],
            ],
        );
    }
}
