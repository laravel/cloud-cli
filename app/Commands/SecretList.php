<?php

namespace App\Commands;

use App\Dto\Secret;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class SecretList extends BaseCommand
{
    protected ?string $jsonDataClass = Secret::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'secret:list';

    protected $description = 'List secrets';

    public function handle()
    {
        $this->ensureClient();

        intro('Secrets');

        answered('Organization', $this->client->meta()->organization()->name);

        $items = spin(
            fn () => collect($this->client->secrets()->list()->collect()),
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
