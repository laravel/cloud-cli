<?php

namespace App\Commands;

use App\Dto\Domain;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class DomainList extends BaseCommand
{
    protected ?string $jsonDataClass = Domain::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'domain:list {environment? : The environment ID}';

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
