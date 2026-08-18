<?php

namespace App\Concerns;

use App\Client\Connector;
use App\Cloud;
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

    protected function ensureClient(bool $ignoreLocalConfig = false, ?string $organization = null)
    {
        $apiToken = $this->resolveApiToken($ignoreLocalConfig, $organization);

        $this->client = new Connector($apiToken);
    }

    protected function ensureApiTokenExists(): void
    {
        if (Cloud::apiTokenFromEnvironment()) {
            return;
        }

        $config = app(ConfigRepository::class);
        $apiTokens = $config->apiTokens();

        if ($apiTokens->isNotEmpty()) {
            return;
        }

        $this->resolveApiToken();
    }

    protected function resolveApiToken(bool $ignoreLocalConfig = false, ?string $organization = null): string
    {
        if ($envToken = Cloud::apiTokenFromEnvironment()) {
            if ($organization) {
                $this->assertEnvironmentTokenBelongsTo($envToken, $organization);
            }

            return $envToken;
        }

        $config = app(ConfigRepository::class);
        $apiTokens = $config->apiTokens();

        if ($apiTokens->hasSole()) {
            return $apiTokens->first();
        }

        if ($apiTokens->hasMany()) {
            $orgs = spin(
                function () use ($apiTokens) {
                    return $apiTokens->mapWithKeys(function ($token) {
                        try {
                            $client = new Connector($token);

                            return [$token => $client->meta()->organization()];
                        } catch (RequestException $e) {
                            if ($e->getResponse()->status() === 401) {
                                return [];
                            }

                            throw $e;
                        }
                    })->filter();
                },
                'Fetching token details',
            );

            if ($orgs->count() < $apiTokens->count()) {
                $config->setApiTokens($orgs->keys());
            }

            if ($orgs->isEmpty()) {
                warning('All stored API tokens are no longer valid. Please re-authenticate with: cloud auth');

                Artisan::call(Auth::class);

                return $this->resolveApiToken($ignoreLocalConfig);
            }

            if ($orgs->count() === 1) {
                return $orgs->keys()->first();
            }

            if ($organization) {
                foreach ($orgs as $token => $org) {
                    if (in_array($organization, [$org->id, $org->name, $org->slug], true)) {
                        return $token;
                    }
                }

                throw new RuntimeException("Unable to resolve organization [{$organization}]. Available organizations: ".$orgs->map(fn ($org) => $org->name)->join(', ').'.');
            }

            if (! $ignoreLocalConfig && $defaultOrganizationId = app(LocalConfig::class)->get('organization_id')) {
                foreach ($orgs as $token => $org) {
                    if ($org->id === $defaultOrganizationId) {
                        return $token;
                    }
                }
            }

            if (! stream_isatty(STDIN) || $this->isNonInteractiveEnvironment()) {
                throw new RuntimeException('Multiple API tokens found. Run `cloud repo:config --organization=<id|name|slug>` to set a default for this repository, or use `cloud auth:token` to manage tokens.');
            }

            $apiToken = select(
                label: 'Organization',
                options: $orgs->mapWithKeys(fn ($organization, $token) => [
                    $token => $organization->name,
                ]),
            );

            return $apiToken;
        }

        if (! stream_isatty(STDIN) && ! $this->isAgentEnvironment()) {
            throw new RuntimeException('Not authenticated. Run `cloud auth`, set '.Cloud::API_TOKEN_ENV_VAR.' in your environment, or run `cloud auth:token --add --token=<token>` to save one.');
        }

        Artisan::call(Auth::class);

        return $this->resolveApiToken($ignoreLocalConfig);
    }

    /**
     * An explicitly named organization is an assertion, not a selector: the environment
     * holds one token, so a mismatch means the wrong credential, not the wrong choice.
     */
    protected function assertEnvironmentTokenBelongsTo(string $token, string $organization): void
    {
        $config = app(ConfigRepository::class);

        try {
            $org = spin(
                fn () => (new Connector($token))->meta()->organization(),
                'Verifying organization',
            );
        } catch (RequestException $e) {
            if ($e->getResponse()->status() === 401) {
                throw new RuntimeException('The API token in '.Cloud::API_TOKEN_ENV_VAR.' was rejected. Check that variable, or unset it to use the tokens in '.$config->path().'.');
            }

            throw $e;
        }

        if (! in_array($organization, [$org->id, $org->name, $org->slug], true)) {
            throw new RuntimeException('The API token in '.Cloud::API_TOKEN_ENV_VAR." belongs to [{$org->name}], not [{$organization}]. Unset that variable to use the tokens in ".$config->path().'.');
        }
    }
}
