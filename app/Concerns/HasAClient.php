<?php

namespace App\Concerns;

use App\Client\Connector;
use App\ConfigRepository;
use App\LocalConfig;

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

            $apiToken = select(
                label: 'Organization',
                options: $orgs->mapWithKeys(fn ($organization, $token) => [
                    $token => $organization->name,
                ]),
            );

            return $apiToken;
        }

        info('No API tokens found.');
        info('Learn how to create an API token: '.config('app.base_url').'/docs/api/authentication#create-an-api-token');

        $apiToken = password(
            label: 'Laravel Cloud API token',
            required: true,
        );

        $config->addApiToken($apiToken);

        info('API token saved to '.$config->path());

        return $apiToken;
    }
}
