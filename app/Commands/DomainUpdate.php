<?php

namespace App\Commands;

use App\Client\Requests\UpdateDomainRequestData;
use App\Dto\Domain;
use App\Exceptions\CommandExitException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class DomainUpdate extends BaseCommand
{
    protected $signature = 'domain:update
                            {domain? : The domain ID or name}
                            {--verification-method= : Verification method (pre_verification or real_time)}
                            {--is-primary= : Set as primary domain (true/false)}
                            {--force : Force update without confirmation}
                            {--json : Output as JSON}';

    protected $description = 'Update a domain';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Domain');

        $domain = $this->resolvers()->domain()->from($this->argument('domain'));

        $this->defineFields($domain);

        foreach ($this->form()->filled() as $key => $value) {
            $this->reportChange(
                $value->label(),
                $value->previousValue(),
                $value->value(),
            );
        }

        $updatedDomain = $this->resolveUpdatedDomain($domain);

        $this->outputJsonIfWanted($updatedDomain);

        success('Domain updated');

        outro($updatedDomain->name);
    }

    protected function resolveUpdatedDomain(Domain $domain): Domain
    {
        if (! $this->isInteractive()) {
            if (! $this->form()->hasAnyValues()) {
                $this->outputErrorOrThrow('No fields to update. Provide at least one option.');

                throw new CommandExitException(self::FAILURE);
            }

            return $this->updateDomain($domain);
        }

        if (! $this->form()->hasAnyValues()) {
            return $this->loopUntilValid(
                fn () => $this->collectDataAndUpdate($domain),
            );
        }

        if (! $this->shouldRunUpdateFromOptions()) {
            error('Update cancelled');

            throw new CommandExitException(self::FAILURE);
        }

        return $this->updateDomain($domain);
    }

    protected function updateDomain(Domain $domain): Domain
    {
        $verificationMethod = $this->form()->get('verification_method');

        if ($verificationMethod !== null && ! in_array($verificationMethod, ['pre_verification', 'real_time'], true)) {
            $this->outputErrorOrThrow("Invalid verification method. Use 'pre_verification' or 'real_time'.");

            throw new CommandExitException(self::FAILURE);
        }

        $isPrimary = $this->form()->get('is_primary');

        spin(
            fn () => $this->client->domains()->update(new UpdateDomainRequestData(
                domainId: $domain->id,
                verificationMethod: $verificationMethod,
                isPrimary: $isPrimary !== null ? filter_var($isPrimary, FILTER_VALIDATE_BOOLEAN) : null,
            )),
            'Updating domain...',
        );

        return $this->client->domains()->get($domain->id);
    }

    protected function shouldRunUpdateFromOptions(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        return confirm('Update the domain?');
    }

    protected function defineFields(Domain $domain): void
    {
        $this->form()->define(
            'verification_method',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => $this->getNewVerificationMethod($value),
            ),
            'verification-method',
        )->setPreviousValue('');

        $this->form()->define(
            'is_primary',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => $this->getNewIsPrimary($value ?? $domain->isPrimary()),
            ),
            'is-primary',
        )->setPreviousValue($domain->isPrimary() ? 'true' : 'false');
    }

    protected function collectDataAndUpdate(Domain $domain): Domain
    {
        $selection = multiselect(
            label: 'What do you want to update?',
            options: collect($this->form()->defined())->mapWithKeys(fn ($field, $key) => [
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

        return $this->updateDomain($domain);
    }

    protected function getNewVerificationMethod(?string $current): string
    {
        return select(
            label: 'Verification method',
            options: [
                'pre_verification' => 'Pre-verification (TXT before domain is pointed to environment)',
                'real_time' => 'Real-time (domain must already point to environment)',
            ],
            default: $current && in_array($current, ['pre_verification', 'real_time'], true) ? $current : 'pre_verification',
        );
    }

    protected function getNewIsPrimary(bool|string $current): bool
    {
        $default = is_string($current) ? filter_var($current, FILTER_VALIDATE_BOOLEAN) : $current;

        return confirm(
            label: 'Set as primary domain?',
            default: $default,
        );
    }
}
