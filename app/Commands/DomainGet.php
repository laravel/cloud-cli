<?php

namespace App\Commands;

use App\Dto\Domain;

use function Laravel\Prompts\intro;

class DomainGet extends BaseCommand
{
    protected ?string $jsonDataClass = Domain::class;

    protected $signature = 'domain:get {domain? : The domain ID or name}';

    protected $description = 'Get domain details';

    public function handle()
    {
        $this->ensureClient();

        intro('Domain Details');

        $domain = $this->resolvers()->domain()->from($this->argument('domain'));

        $this->outputJsonIfWanted($domain);

        dataList([
            'ID' => $domain->id,
            'Name' => $domain->name,
            'Type' => $domain->type,
            'Status' => $domain->status(),
            'Primary' => $domain->isPrimary() ? 'Yes' : 'No',
            'Verification' => $domain->verificationStatus(),
            'Created At' => $domain->createdAt?->toIso8601String() ?? '—',
            'Last Verified At' => $domain->lastVerifiedAt?->toIso8601String() ?? '—',
        ]);
    }
}
