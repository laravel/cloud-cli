<?php

namespace App\Commands;

use App\Client\Requests\AttachEnvironmentSecretsRequestData;
use App\Dto\Environment;
use App\Dto\Secret;
use Illuminate\Support\Collection;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\spin;

class EnvironmentSecretAttach extends BaseCommand
{
    protected ?string $jsonDataClass = Environment::class;

    protected $signature = 'environment-secret:attach
                            {environment? : The environment ID or name}
                            {secrets?* : The secret IDs to attach}';

    protected $description = 'Attach secrets to an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Attaching Secrets');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $secrets = $this->resolveSecrets();

        $updatedEnvironment = spin(
            fn () => $this->client->environments()->attachSecrets(
                new AttachEnvironmentSecretsRequestData(
                    environmentId: $environment->id,
                    secrets: collect($secrets)->pluck('id')->all(),
                ),
            ),
            'Attaching secrets...',
        );

        $this->outputJsonIfWanted($updatedEnvironment);

        success('Secrets attached: '.collect($secrets)->pluck('key')->join(', '));
    }

    /**
     * @return list<Secret>
     */
    protected function resolveSecrets(): array
    {
        $secrets = spin(
            fn () => collect($this->client->secrets()->list()->collect()),
            'Fetching secrets...',
        );

        if ($secrets->isEmpty()) {
            $this->failAndExit('No secrets found. Create one with `cloud secret:create`.');
        }

        $identifiers = $this->argument('secrets');

        if ($identifiers === []) {
            return $this->selectSecrets($secrets);
        }

        $resolver = $this->resolvers()->secret();

        // Only IDs are accepted: secret names are not unique within an organization.
        return collect($identifiers)->map(function (string $identifier) use ($secrets, $resolver) {
            $secret = $resolver->fromCollection($secrets, $identifier);

            if (! $secret) {
                $this->failAndExit("Unable to resolve secret: {$identifier}. Provide a secret ID; run `cloud secret:list --json` to see them.");
            }

            return $secret;
        })->unique('id')->values()->all();
    }

    /**
     * @return list<Secret>
     */
    protected function selectSecrets(Collection $secrets): array
    {
        // Names are not unique, so each option carries its ID.
        $options = $secrets->mapWithKeys(fn (Secret $secret) => [$secret->id => "{$secret->key} ({$secret->id})"])->toArray();

        if (! $this->isInteractive()) {
            $this->failAndExit('At least one secret is required. Provide secret IDs as arguments. Available secrets: '.implode(', ', array_keys($options)).'.');
        }

        $selected = multiselect(
            label: 'Secrets',
            options: $options,
            required: true,
        );

        return $secrets->whereIn('id', $selected)->values()->all();
    }
}
