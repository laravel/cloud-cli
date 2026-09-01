<?php

namespace App\Commands;

use App\Client\Requests\UpdateSecretRequestData;
use App\Concerns\AcceptsPipedInput;
use App\Dto\Secret;
use App\Exceptions\CommandExitException;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\password;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class SecretUpdate extends BaseCommand
{
    use AcceptsPipedInput;

    protected ?string $jsonDataClass = Secret::class;

    protected $signature = 'secret:update
                            {secret? : The secret ID}
                            {--name= : The secret name, e.g. STRIPE_KEY}
                            {--value= : A new secret value (may also be piped to STDIN)}
                            {--notes= : Notes describing the secret (max 500 characters)}
                            {--force : Force update without confirmation}';

    protected $description = 'Update a secret';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Secret');

        $this->fillOptionFromStdin('value');

        $secret = $this->resolvers()->secret()->from($this->argument('secret'));

        $this->defineFields($secret);

        foreach ($this->form()->filled() as $key => $field) {
            // The plaintext value must never be echoed back to the terminal.
            $this->reportChange(
                $field->label(),
                $key === 'value' ? null : $field->previousValue(),
                $key === 'value' ? '••••••••' : $field->value(),
            );
        }

        $updatedSecret = $this->runUpdate(
            fn () => $this->updateSecret($secret),
            fn () => $this->collectDataAndUpdate($secret),
        );

        $this->outputJsonIfWanted($updatedSecret);

        success("Secret updated: {$updatedSecret->key}");
    }

    protected function updateSecret(Secret $secret): Secret
    {
        $value = $this->form()->get('value');

        // The key pair is only needed when a new value is being encrypted.
        $publicKey = $value === null ? null : spin(
            fn () => $this->client->secrets()->publicKey(),
            'Fetching public key...',
        );

        return spin(
            fn () => $this->client->secrets()->update(
                new UpdateSecretRequestData(
                    secretId: $secret->id,
                    // The API requires the name on every update, so resend the current one when it is unchanged.
                    key: $this->form()->get('name') ?? $secret->key,
                    value: $publicKey?->encrypt($value),
                    keyPairId: $publicKey?->id,
                    notes: $this->form()->get('notes'),
                ),
            ),
            'Updating secret...',
        );
    }

    protected function defineFields(Secret $secret): void
    {
        $this->form()->define(
            'name',
            fn ($resolver) => $resolver->fromInput(fn (?string $value) => text(
                label: 'Secret name',
                default: $value ?? $secret->key,
                required: true,
            )),
        )->setPreviousValue($secret->key);

        $this->form()->define(
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

        $this->form()->define(
            'notes',
            fn ($resolver) => $resolver->fromInput(fn (?string $value) => text(
                label: 'Notes',
                placeholder: 'Optional',
                default: $value ?? $secret->notes ?? '',
                validate: fn (string $notes) => strlen($notes) > 500
                    ? 'The notes cannot exceed 500 characters.'
                    : null,
            )),
        )->setPreviousValue($secret->notes);
    }

    protected function collectDataAndUpdate(Secret $secret): Secret
    {
        $selection = multiselect(
            label: 'What do you want to update?',
            options: collect($this->form()->defined())->mapWithKeys(fn ($field) => [
                $field->key => $field->label(),
            ])->toArray(),
        );

        if (empty($selection)) {
            $this->outputErrorOrThrow('No fields to update. Select at least one option.');

            throw new CommandExitException(self::FAILURE);
        }

        foreach ($selection as $optionName) {
            $this->form()->prompt($optionName);
        }

        return $this->updateSecret($secret);
    }
}
