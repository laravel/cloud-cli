<?php

namespace App\Commands;

use App\Dto\Domain;
use App\Support\UpdateFields;

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

        $fields = $this->getFieldDefinitions($domain);

        $data = [];

        foreach ($fields as $optionName => $field) {
            if ($this->option($optionName)) {
                $rawValue = $this->option($optionName);
                $value = match ($field['key']) {
                    'is_primary' => filter_var($rawValue, FILTER_VALIDATE_BOOLEAN),
                    'verification_method' => in_array($rawValue, ['pre_verification', 'real_time'], true) ? $rawValue : null,
                    default => $rawValue,
                };

                if ($field['key'] === 'verification_method' && $value === null) {
                    $this->outputErrorOrThrow("Invalid verification method. Use 'pre_verification' or 'real_time'.");

                    exit(self::FAILURE);
                }

                if ($value !== null) {
                    $data[$field['key']] = $value;

                    $this->reportChange(
                        $field['label'],
                        (string) $field['current'],
                        (string) $rawValue,
                    );
                }
            }
        }

        $updatedDomain = $this->resolveUpdatedDomain($domain, $fields, $data);

        $this->outputJsonIfWanted($updatedDomain);

        success('Domain updated');

        outro($updatedDomain->name);
    }

    protected function resolveUpdatedDomain(Domain $domain, array $fields, array $data): Domain
    {
        if (! $this->isInteractive()) {
            if (empty($data)) {
                $this->outputErrorOrThrow('No fields to update. Provide at least one option.');

                exit(self::FAILURE);
            }

            return $this->updateDomain($domain, $data);
        }

        if (empty($data)) {
            return $this->loopUntilValid(
                fn () => $this->collectDataAndUpdate($fields, $domain),
            );
        }

        if (! $this->shouldRunUpdateFromOptions()) {
            error('Update cancelled');

            exit(self::FAILURE);
        }

        return $this->updateDomain($domain, $data);
    }

    protected function updateDomain(Domain $domain, array $data): Domain
    {
        spin(
            fn () => $this->client->domains()->update($domain->id, $data),
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

    protected function getFieldDefinitions(Domain $domain): array
    {
        $fields = new UpdateFields;

        $fields->add('verification-method', fn ($value) => $this->getNewVerificationMethod($value))
            ->currentValue('')
            ->dataKey('verification_method')
            ->label('Verification method');
        $fields->add('is-primary', fn ($value) => $this->getNewIsPrimary($value))
            ->currentValue($domain->isPrimary() ? 'true' : 'false')
            ->dataKey('is_primary')
            ->label('Primary');

        return $fields->get();
    }

    protected function collectDataAndUpdate(array $fields, Domain $domain): Domain
    {
        $selection = multiselect(
            label: 'What do you want to update?',
            options: collect($fields)->mapWithKeys(fn ($field, $key) => [
                $key => $field['label'],
            ])->toArray(),
        );

        if (empty($selection)) {
            $this->outputErrorOrThrow('No fields to update. Select at least one option.');

            exit(self::FAILURE);
        }

        foreach ($selection as $optionName) {
            $field = $fields[$optionName];

            $this->addParam($field['key'], fn ($resolver) => $resolver->fromInput(
                fn ($value) => ($field['prompt'])($value ?? $field['current']),
            ));
        }

        $params = $this->getParams();

        if (isset($params['is_primary'])) {
            $params['is_primary'] = filter_var($params['is_primary'], FILTER_VALIDATE_BOOLEAN);
        }

        return $this->updateDomain($domain, $params);
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
