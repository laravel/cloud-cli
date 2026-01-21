<?php

namespace App\Commands;

use App\Concerns\HasAClient;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

class DomainGet extends BaseCommand
{
    use HasAClient;

    protected $signature = 'domain:get {domain : The domain ID} {--json : Output as JSON}';

    protected $description = 'Get domain details';

    public function handle()
    {
        $this->ensureClient();

        $this->intro('Domain Details');

        $domain = spin(
            fn () => $this->client->getDomain($this->argument('domain')),
            'Fetching domain...'
        );

        if ($this->option('json')) {
            $this->line(json_encode([
                'id' => $domain->id,
                'domain' => $domain->domain,
                'status' => $domain->status,
                'is_primary' => $domain->isPrimary,
                'verification_status' => $domain->verificationStatus,
                'created_at' => $domain->createdAt?->toIso8601String(),
                'updated_at' => $domain->updatedAt?->toIso8601String(),
            ], JSON_PRETTY_PRINT));

            return;
        }

        info("Domain: {$domain->domain}");
        $this->line("ID: {$domain->id}");
        $this->line("Status: {$domain->status}");
        $this->line('Primary: '.($domain->isPrimary ? 'Yes' : 'No'));
        $this->line("Verification: {$domain->verificationStatus}");
    }
}
