<?php

namespace App\Commands;

use App\Concerns\ManagesResend;
use App\Dto\ResendDomain;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\warning;

class ResendDomains extends BaseCommand
{
    use ManagesResend;

    protected ?string $jsonDataClass = ResendDomain::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'resend:domains
                            {--name= : Filter by domain name}
                            {--status= : Filter by status, e.g. verified}';

    protected $description = 'List Resend sending domains';

    public function handle()
    {
        $this->ensureClient();

        intro('Resend Sending Domains');

        $domains = $this->whenResendIsAvailable(fn () => $this->fetchList(
            fn () => $this->client->resend()->domains($this->option('name'), $this->option('status')),
            'Fetching sending domains...',
        ));

        $this->outputJsonIfWanted($domains->toArray());

        if ($domains->isEmpty()) {
            warning('No sending domains found.');

            return self::SUCCESS;
        }

        dataTable(
            headers: ['ID', 'Name', 'Region', 'Status', 'Last Verified'],
            rows: $domains->map(fn (ResendDomain $domain) => [
                $domain->id,
                $domain->name,
                $domain->region,
                $domain->status,
                $domain->lastVerifiedAt?->toIso8601String() ?? '—',
            ])->toArray(),
        );
    }
}
