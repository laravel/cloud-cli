<?php

namespace App\Commands;

use App\Dto\Secret;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class EnvironmentSecretList extends BaseCommand
{
    protected ?string $jsonDataClass = Secret::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'environment-secret:list
                            {environment? : The environment ID or name}';

    protected $description = 'List the secrets attached to an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Environment Secrets');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        // The environment carries its secrets as an include; there is no endpoint of their own.
        $environment = spin(
            fn () => $this->client->environments()->include('secrets')->get($environment->id),
            'Fetching secrets...',
        );

        $items = collect($environment->secrets);

        $this->outputJsonIfWanted($items->toArray());

        if ($items->isEmpty()) {
            warning('No secrets attached to this environment.');

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
