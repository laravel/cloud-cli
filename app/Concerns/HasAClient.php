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
use function Laravel\Prompts\warning;

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

        // When there's a single token, validate it before using it
        if ($apiTokens->hasSole()) {
            $token = $apiTokens->first();

            if ($this->isValidToken($token)) {
                return $token;
            }

            // Token is invalid/expired, remove it and fall through to re-auth
            $config->removeApiToken($token);
            $apiTokens = collect();
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

            if ($orgs->isEmpty()) {
                // All tokens expired, fall through to re-auth
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

    /**
     * Check whether a token is still valid by making a lightweight API call.
     */
    protected function isValidToken(string $token): bool
    {
        try {
            (new Connector($token))->meta()->organization();

            return true;
        } catch (RequestException) {
            return false;
        }
    }
}
