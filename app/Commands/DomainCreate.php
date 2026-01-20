<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use Illuminate\Http\Client\RequestException;
use Laravel\Prompts\Concerns\Colors;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;

class DomainCreate extends Command
{
    use Colors;
    use HasAClient;

    protected $signature = 'domain:create
                            {environment : The environment ID}
                            {domain : The domain name}
                            {--json : Output as JSON}';

    protected $description = 'Create a new domain';

    public function handle()
    {
        $this->ensureClient();

        intro('Creating domain');

        try {
            $domain = spin(
                fn () => $this->client->createDomain(
                    $this->argument('environment'),
                    $this->argument('domain')
                ),
                'Creating domain...'
            );

            if ($this->option('json')) {
                $this->line(json_encode([
                    'id' => $domain->id,
                    'domain' => $domain->domain,
                    'status' => $domain->status,
                    'created_at' => $domain->createdAt?->toIso8601String(),
                ], JSON_PRETTY_PRINT));

                return;
            }

            outro("Domain created: {$domain->domain}");
        } catch (RequestException $e) {
            if ($e->response?->status() === 422) {
                $errors = $e->response->json()['errors'] ?? [];
                foreach ($errors as $field => $messages) {
                    error(ucwords($field).': '.implode(', ', $messages));
                }
            } else {
                error('Failed to create domain: '.$e->getMessage());
            }

            return 1;
        }
    }
}
