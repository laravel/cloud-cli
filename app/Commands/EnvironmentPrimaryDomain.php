<?php

namespace App\Commands;

use App\Client\Requests\UpdateEnvironmentPrimaryDomainRequestData;
use App\Dto\Domain;
use App\Dto\Environment;
use Illuminate\Support\Collection;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class EnvironmentPrimaryDomain extends BaseCommand
{
    protected const NONE = 'none';

    protected ?string $jsonDataClass = Environment::class;

    protected $signature = 'environment:primary-domain
                            {environment? : The environment ID or name}
                            {--domain= : The domain ID or name to make primary}
                            {--clear : Clear the primary domain (the domain stays attached)}';

    protected $description = "Set or clear an environment's primary domain";

    protected $aliases = ['env:primary-domain'];

    public function handle()
    {
        $this->ensureClient();

        intro('Setting Primary Domain');

        if ($this->option('clear') && $this->option('domain')) {
            $this->failAndExit('Pass either --domain or --clear, not both.');
        }

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        if ($this->option('clear')) {
            return $this->clear($environment);
        }

        $domains = $this->fetchList(
            fn () => $this->client->domains()->list($environment->id),
            'Fetching domains...',
        );

        $domain = $this->option('domain')
            ? $this->findDomain($environment, $domains, $this->option('domain'))
            : $this->pickDomain($environment, $domains);

        if (! $domain) {
            return $this->clear($environment);
        }

        if ($domain->id === $environment->primaryDomainId) {
            warning("{$domain->name} is already the primary domain of {$environment->name}.");

            $this->outputJsonIfWanted($environment);

            return self::SUCCESS;
        }

        $updated = spin(
            function () use ($environment, $domain) {
                $this->client->environments()->updatePrimaryDomain(
                    new UpdateEnvironmentPrimaryDomainRequestData(
                        environmentId: $environment->id,
                        domainId: $domain->id,
                    ),
                );

                return $this->client->environments()->get($environment->id);
            },
            'Setting primary domain...',
        );

        $this->outputJsonIfWanted($updated);

        success("{$domain->name} is now the primary domain of {$updated->name}.");

        return self::SUCCESS;
    }

    protected function clear(Environment $environment): int
    {
        if (! $environment->primaryDomainId) {
            warning("{$environment->name} has no primary domain.");

            $this->outputJsonIfWanted($environment);

            return self::SUCCESS;
        }

        $updated = spin(
            function () use ($environment) {
                $this->client->environments()->clearPrimaryDomain($environment->id);

                return $this->client->environments()->get($environment->id);
            },
            'Clearing primary domain...',
        );

        $this->outputJsonIfWanted($updated);

        success("Primary domain cleared for {$updated->name}.");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Domain>  $domains
     */
    protected function findDomain(Environment $environment, Collection $domains, string $idOrName): Domain
    {
        $domain = $domains->firstWhere('id', $idOrName) ?? $domains->firstWhere('name', $idOrName);

        if (! $domain) {
            $this->failAndExit("Domain {$idOrName} is not attached to {$environment->name}. Run `cloud domain:list {$environment->id} --json` to see its domains.");
        }

        return $domain;
    }

    /**
     * Only a root domain can be primary, so the others aren't offered. Returns null to clear.
     *
     * @param  Collection<int, Domain>  $domains
     */
    protected function pickDomain(Environment $environment, Collection $domains): ?Domain
    {
        $this->ensureInteractive('Provide a domain with --domain, or pass --clear.');

        $options = $domains
            ->filter(fn (Domain $domain) => $domain->type === 'root')
            ->mapWithKeys(fn (Domain $domain) => [$domain->id => $domain->name]);

        if ($environment->primaryDomainId) {
            $options->put(self::NONE, 'None (clear the primary domain)');
        }

        if ($options->isEmpty()) {
            $this->failAndExit("{$environment->name} has no root domains to make primary. Add one with `cloud domain:create`.");
        }

        $selected = select(
            label: 'Primary domain',
            options: $options->all(),
            default: $environment->primaryDomainId ?? $options->keys()->first(),
        );

        return $selected === self::NONE ? null : $domains->firstWhere('id', $selected);
    }
}
