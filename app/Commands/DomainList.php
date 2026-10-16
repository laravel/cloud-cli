<?php

namespace App\Commands;

use App\Concerns\FiltersLists;
use App\Dto\Domain;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\warning;

class DomainList extends BaseCommand
{
    use FiltersLists;

    protected ?string $jsonDataClass = Domain::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'domain:list
                            {environment? : The environment ID}
                            {--name= : Filter by name}
                            {--hostname-status= : Filter by hostname status (pending|verified|failed|disabled)}
                            {--ssl-status= : Filter by SSL status (pending|verified|failed|disabled)}
                            {--origin-status= : Filter by origin status (pending|verified|failed|disabled)}';

    protected $description = 'List all domains for an environment';

    public function handle()
    {
        $this->ensureClient();

        $filters = [
            'name' => $this->filterOption('name'),
            'hostnameStatus' => $this->filterOption('hostname-status', ['pending', 'verified', 'failed', 'disabled']),
            'sslStatus' => $this->filterOption('ssl-status', ['pending', 'verified', 'failed', 'disabled']),
            'originStatus' => $this->filterOption('origin-status', ['pending', 'verified', 'failed', 'disabled']),
        ];

        intro('Domains');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $items = $this->fetchList(
            fn () => $this->client->domains()->list($environment->id, ...$filters),
            'Fetching domains...',
        );

        $this->outputJsonIfWanted($items);

        if ($items->isEmpty()) {
            warning('No domains found.');

            return self::SUCCESS;
        }

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
