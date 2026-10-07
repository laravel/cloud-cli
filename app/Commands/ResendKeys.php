<?php

namespace App\Commands;

use App\Concerns\ManagesResend;
use App\Dto\ResendSendingKey;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class ResendKeys extends BaseCommand
{
    use ManagesResend;

    protected ?string $jsonDataClass = ResendSendingKey::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'resend:keys
                            {environment? : The environment ID or name}';

    protected $description = 'List the Resend sending keys an environment can reuse';

    public function handle()
    {
        $this->ensureClient();

        intro('Reusable Sending Keys');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $keys = $this->whenResendIsAvailable(fn () => spin(
            fn () => $this->client->resend()->reusableKeys($environment->id),
            'Fetching sending keys...',
        ));

        $this->outputJsonIfWanted($keys);

        if ($keys === []) {
            warning('No sending keys to reuse. Attaching Resend creates a new one.');

            return self::SUCCESS;
        }

        dataTable(
            headers: ['ID', 'Name', 'Environments'],
            rows: collect($keys)->map(fn (ResendSendingKey $key) => [
                $key->id,
                $key->name,
                $key->environmentCount,
            ])->toArray(),
        );
    }
}
