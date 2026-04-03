<?php

namespace App\Concerns;

use App\Client\Connector;
use App\Commands\Auth;
use App\ConfigRepository;
use App\LocalConfig;
use App\Support\DetectsNonInteractiveEnvironments;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Saloon\Exceptions\Request\RequestException;

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

        if ($apiTokens->hasMany()) {
            // Validate all tokens and remove invalid ones
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

            // If all tokens expired, fall through to re-auth below
            if ($orgs->count() === 1) {
                return $orgs->keys()->first();
            } elseif ($orgs->isNotEmpty()) {
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
        }

        if (! stream_isatty(STDIN) && ! $this->isAgentEnvironment()) {
            throw new RuntimeException('Not authenticated. Run `cloud auth` or `cloud auth:token --add` to add an API token.');
        }

        Artisan::call(Auth::class);

        return $this->resolveApiToken($ignoreLocalConfig);
    }
}
