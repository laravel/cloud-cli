<?php

namespace App\Commands;

use App\Concerns\FiltersLists;
use App\Dto\Secret;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\warning;

class SecretList extends BaseCommand
{
    use FiltersLists;

    protected ?string $jsonDataClass = Secret::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'secret:list
                            {--key= : Filter by key}
                            {--sort= : Sort order (key|-key|created_at|-created_at)}';

    protected $description = 'List secrets';

    public function handle()
    {
        $this->ensureClient();

        $filters = [
            'key' => $this->filterOption('key'),
            'sort' => $this->filterOption('sort', ['key', '-key', 'created_at', '-created_at']),
        ];

        intro('Secrets');

        answered('Organization', $this->client->meta()->organization()->name);

        $items = $this->fetchList(
            fn () => $this->client->secrets()->list(...$filters),
            'Fetching secrets...',
        );

        $this->outputJsonIfWanted($items->toArray());

        if ($items->isEmpty()) {
            warning('No secrets found.');

            return self::SUCCESS;
        }

        dataTable(
            headers: ['ID', 'Name', 'Notes', 'Created At'],
            rows: $items->map(fn (Secret $secret) => [
                $secret->id,
                $secret->key,
                $secret->notes ?? '—',
                $secret->createdAt?->toIso8601String() ?? '—',
            ])->toArray(),
        );
    }
}
