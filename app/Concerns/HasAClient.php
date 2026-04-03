<?php

namespace App\Concerns;

use App\Client\Connector;
use App\ConfigRepository;
use App\LocalConfig;
use App\Support\DetectsNonInteractiveEnvironments;
use RuntimeException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

trait HasAClient
{
    use DetectsNonInteractiveEnvironments;

    protected Connector $client;

    protected function ensureClient(bool $ignoreLocalConfig = false)
    {
        $apiToken = $this->resolveApiToken($ignoreLocalConfig);

        $this->client = new Connector($apiToken);
    }

    protected function ensureApiTokenExists(): void
    {
        $config = app(ConfigRepository::class);
        $apiTokens = $config->apiTokens();

        if ($apiTokens->isNotEmpty()) {
            return;
        }

        $this->resolveApiToken();
    }

    protected function resolveApiToken(bool $ignoreLocalConfig = false): string
    {
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

            if (! stream_isatty(STDIN) || $this->isNonInteractiveEnvironment()) {
                throw new RuntimeException('Multiple API tokens found. Set organization_id in .cloud/config.json or use `cloud auth:token` to manage tokens.');
            }

            $apiToken = select(
                label: 'Organization',
                options: $orgs->mapWithKeys(fn ($organization, $token) => [
                    $token => $organization->name,
                ]),
            );

            return $apiToken;
        }

        if (! stream_isatty(STDIN) || $this->isNonInteractiveEnvironment()) {
            throw new RuntimeException('Not authenticated. Run `cloud auth` or `cloud auth:token --add` to add an API token.');
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
