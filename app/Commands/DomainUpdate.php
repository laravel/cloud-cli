<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use Illuminate\Http\Client\RequestException;

use function Laravel\Prompts\error;
use function Laravel\Prompts\spin;

class DomainUpdate extends BaseCommand
{
    use HasAClient;

    protected $signature = 'domain:update
                            {domain : The domain ID}
                            {--is-primary= : Set as primary domain (true/false)}
                            {--json : Output as JSON}';

    protected $description = 'Update a domain';

    public function handle()
    {
        $this->ensureClient();

        $this->intro('Updating domain');

        $data = [];

        if ($this->option('is-primary') !== null) {
            $data['is_primary'] = filter_var($this->option('is-primary'), FILTER_VALIDATE_BOOLEAN);
        }

        if (empty($data)) {
            error('No fields to update. Provide at least one option.');

            return 1;
        }

        try {
            $domain = spin(
                fn () => $this->client->updateDomain($this->argument('domain'), $data),
                'Updating domain...'
            );

            if ($this->option('json')) {
                $this->line(json_encode([
                    'id' => $domain->id,
                    'domain' => $domain->domain,
                    'is_primary' => $domain->isPrimary,
                    'updated_at' => $domain->updatedAt?->toIso8601String(),
                ], JSON_PRETTY_PRINT));

                return;
            }

            $this->outro("Domain updated: {$domain->domain}");
        } catch (RequestException $e) {
            if ($e->response?->status() === 422) {
                $errors = $e->response->json()['errors'] ?? [];
                foreach ($errors as $field => $messages) {
                    error(ucwords($field).': '.implode(', ', $messages));
                }
            } else {
                error('Failed to update domain: '.$e->getMessage());
            }

            return 1;
        }
    }
}
