<?php

namespace App\Concerns;

use App\Client\Connector;
use App\ConfigRepository;
use App\LocalConfig;
use Saloon\Exceptions\Request\RequestException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

trait HasAClient
{
    protected Connector $client;

    protected function ensureClient(bool $ignoreLocalConfig = false)
    {
        $apiToken = $this->resolveApiToken($ignoreLocalConfig);

        $this->client = new Connector($apiToken);
    }

    protected function ensureApiTokenExists(): void
    {
        // If a token is available via env var, no need to check config
        $envToken = getenv('LARAVEL_CLOUD_API_TOKEN');
        if ($envToken !== false && $envToken !== '') {
            return;
        }

        $config = app(ConfigRepository::class);
        $apiTokens = $config->apiTokens();

        if ($apiTokens->isNotEmpty()) {
            return;
        }

        $this->resolveApiToken();
    }

    protected function resolveApiToken(bool $ignoreLocalConfig = false): string
    {
        // --token flag takes highest priority
        if ($this instanceof \Symfony\Component\Console\Command\Command
            && $this->getDefinition()->hasOption('token')
            && ($flagToken = $this->option('token'))
        ) {
            return $flagToken;
        }

        // LARAVEL_CLOUD_API_TOKEN env var takes second priority
        $envToken = getenv('LARAVEL_CLOUD_API_TOKEN');
        if ($envToken !== false && $envToken !== '') {
            return $envToken;
        }

        $config = app(ConfigRepository::class);
        $apiTokens = $config->apiTokens();

        if ($apiTokens->hasSole()) {
            return $apiTokens->first();
        }

        if ($apiTokens->containsManyItems()) {
            $tokenEntries = $config->apiTokenEntries();
            $hasCachedOrgNames = $tokenEntries->every(fn (array $e) => $e['organization_name'] !== '');

            if ($hasCachedOrgNames) {
                // Use cached org metadata — no API calls needed
                $orgs = $tokenEntries->mapWithKeys(fn (array $e) => [
                    $e['token'] => new \App\Dto\Organization(
                        id: $e['organization_id'],
                        name: $e['organization_name'],
                        slug: '',
                    ),
                ]);
            } else {
                // Backwards compatibility: legacy tokens without cached org names
                $validTokens = collect();
                $orgs = spin(
                    function () use ($apiTokens, &$validTokens) {
                        return $apiTokens->mapWithKeys(function ($token) use (&$validTokens) {
                            try {
                                $client = new Connector($token);
                                $org = $client->meta()->organization();
                                $validTokens->push($token);

                                return [$token => $org];
                            } catch (RequestException) {
                                return [];
                            }
                        })->filter();
                    },
                    'Fetching token details',
                );

                // Persist cleanup if any tokens were removed
                if ($validTokens->count() < $apiTokens->count()) {
                    $config->setApiTokens($validTokens);
                }

                // Upgrade legacy tokens: cache the org names we just fetched
                if ($orgs->isNotEmpty()) {
                    $upgradedTokens = $orgs->map(fn ($org, $token) => [
                        'token' => $token,
                        'organization_name' => $org->name,
                        'organization_id' => $org->id,
                    ])->values();
                    $config->setApiTokens($upgradedTokens);
                }
            }

            if ($orgs->isEmpty()) {
                warning('All stored API tokens are no longer valid. Please re-authenticate.');
            } elseif ($orgs->count() === 1) {
                return $orgs->keys()->first();
            } else {
                if (! $ignoreLocalConfig && $defaultOrganizationId = app(LocalConfig::class)->get('organization_id')) {
                    foreach ($orgs as $token => $organization) {
                        if ($organization->id === $defaultOrganizationId) {
                            return $token;
                        }
                    }
                }

                $apiToken = select(
                    label: 'Organization',
                    options: $orgs->mapWithKeys(fn ($organization, $token) => [
                        $token => $organization->name,
                    ]),
                );

                return $apiToken;
            }
        }

        info('No API tokens found.');
        info('Learn how to create an API token: https://cloud.laravel.com/docs/api/authentication#create-an-api-token');

        $apiToken = password(
            label: 'Laravel Cloud API token',
            required: true,
        );

        $config->addApiToken($apiToken);

        info('API token saved to '.$config->path());

        return $apiToken;
    }
}
