<?php

namespace App\Concerns;

use App\Client\Connector;
use App\ConfigRepository;
use App\LocalConfig;
use Symfony\Component\Console\Command\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

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
        // --token flag takes highest priority (check argv since middleware doesn't have command context)
        foreach ($_SERVER['argv'] ?? [] as $arg) {
            if (str_starts_with($arg, '--token=') || $arg === '--token') {
                return;
            }
        }

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
        if ($this instanceof Command
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
            $orgs = spin(
                function () use ($apiTokens) {
                    return $apiTokens->mapWithKeys(function ($token) {
                        $client = new Connector($token);

                        return [$token => $client->meta()->organization()];
                    });
                },
                'Fetching token details',
            );

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
