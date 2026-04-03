<?php

namespace App\Commands;

use App\Dto\Domain;
use Illuminate\Http\Client\RequestException;

use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class DomainVerify extends BaseCommand
{
    protected ?string $jsonDataClass = Domain::class;

    protected $signature = 'domain:verify {domain? : The domain ID}';

    protected $description = 'Verify domain DNS records are properly set up';

    public function handle()
    {
        $this->ensureClient();

        intro('Verifying Domain');

        $domain = $this->resolvers()->domain()->from($this->argument('domain'));

        try {
            $domain = spin(
                fn () => $this->client->domains()->verify($domain->id),
                'Verifying domain...',
            );

            $this->outputJsonIfWanted($domain);

            success("Domain verification request completed: {$domain->name}");
        } catch (RequestException $e) {
            error('Failed to verify domain: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
