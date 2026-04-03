<?php

namespace App\Commands;

use App\Client\Requests\UpdateDomainRequestData;
use App\Dto\Domain;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class DomainUpdate extends BaseCommand
{
    protected ?string $jsonDataClass = Domain::class;

    protected $signature = 'domain:update
                            {domain? : The domain ID or name}
                            {--verification-method= : Verification method (pre_verification or real_time)}
                            {--force : Force update without confirmation}';

    protected $description = 'Update a domain';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Domain');

        $domain = $this->resolvers()->domain()->from($this->argument('domain'));

        $this->defineFields($domain);

        foreach ($this->form()->filled() as $value) {
            $this->reportChange(
                $value->label(),
                $value->previousValue(),
                $value->value(),
            );
        }

        $updatedDomain = $this->runUpdate(
            fn () => $this->updateDomain($domain),
            fn () => $this->collectDataAndUpdate($domain),
        );

        $this->outputJsonIfWanted($updatedDomain);

        success("Domain updated: {$updatedDomain->name}");
    }

    protected function updateDomain(Domain $domain): Domain
    {
        spin(
            fn () => $this->client->domains()->update(
                new UpdateDomainRequestData(
                    domainId: $domain->id,
                    verificationMethod: $this->form()->get('verification_method'),
                ),
            ),
            'Updating domain...',
        );

        return $this->client->domains()->get($domain->id);
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
    }

    protected function collectDataAndUpdate(Domain $domain): Domain
    {
        foreach ($this->form()->defined() as $field) {
            $this->form()->prompt($field->key);
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
