<?php

namespace App\Commands;

use App\Client\Requests\CreateSecretRequestData;
use App\Dto\Secret;
use App\Dto\SecretPublicKey;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\password;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class SecretCreate extends BaseCommand
{
    protected ?string $jsonDataClass = Secret::class;

    protected $signature = 'secret:create
                            {--name= : The secret name, e.g. STRIPE_KEY}
                            {--value= : The secret value}
                            {--notes= : Notes describing the secret (max 500 characters)}';

    protected $description = 'Create a new secret';

    public function handle()
    {
        $this->ensureClient();

        intro('Creating Secret');

        $publicKey = spin(
            fn () => $this->client->secrets()->publicKey(),
            'Fetching public key...',
        );

        $secret = $this->loopUntilValid(fn () => $this->createSecret($publicKey));

        $this->outputJsonIfWanted($secret);

        success("Secret created: {$secret->key}");
    }

    protected function createSecret(SecretPublicKey $publicKey)
    {
        $this->form()->prompt(
            'key',
            fn ($resolver) => $resolver->fromInput(fn (?string $value) => text(
                label: 'Secret name',
                placeholder: 'STRIPE_KEY',
                default: $value ?? '',
                required: true,
            )),
            'name',
        );

        $this->form()->prompt(
            'value',
            fn ($resolver) => $resolver->fromInput(fn (?string $value) => $this->option('show-sensitive')
                ? text(
                    label: 'Value',
                    default: $value ?? '',
                    required: true,
                    hint: 'The value is encrypted before it leaves your machine.',
                )
                : password(
                    label: 'Value',
                    required: true,
                    hint: 'The value is encrypted before it leaves your machine.',
                ),
            ),
        );

        $this->form()->prompt(
            'notes',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => text(
                    label: 'Notes',
                    placeholder: 'Optional',
                    default: $value ?? '',
                    validate: fn (string $notes) => strlen($notes) > 500
                        ? 'The notes cannot exceed 500 characters.'
                        : null,
                ))
                ->nonInteractively(fn () => ''),
        );

        $notes = $this->form()->get('notes');

        return spin(
            fn () => $this->client->secrets()->create(
                new CreateSecretRequestData(
                    keyPairId: $publicKey->id,
                    key: $this->form()->get('key'),
                    value: $publicKey->encrypt($this->form()->get('value')),
                    notes: $notes === '' ? null : $notes,
                ),
            ),
            'Creating secret...',
        );
    }
}
