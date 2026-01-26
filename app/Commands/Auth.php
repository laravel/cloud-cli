<?php

namespace App\Commands;

use App\CloudClient;
use App\ConfigRepository;
use App\Contracts\NoAuthRequired;
use Illuminate\Support\Collection;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class Auth extends BaseCommand implements NoAuthRequired
{
    protected $signature = 'auth
                            {--add : Add a new API token}
                            {--remove : Remove an API token}
                            {--list : List API tokens}';

    protected $description = 'Manage Laravel Cloud API tokens';

    protected ConfigRepository $config;

    public function handle()
    {
        $this->config = app(ConfigRepository::class);
        $tokens = $this->config->apiTokens();

        intro('Laravel Cloud API Tokens');

        if ($this->option('add')) {
            $this->addToken($tokens);

            return;
        }

        if ($this->option('remove')) {
            $this->removeToken($tokens);

            return;
        }

        if ($this->option('list')) {
            $this->listTokens($tokens);

            return;
        }

        $action = select(
            label: 'What would you like to do?',
            options: [
                'add' => 'Add a new API token',
                'remove' => 'Remove an API token',
                'list' => 'List API tokens',
            ],
        );

        match ($action) {
            'add' => $this->addToken($tokens),
            'remove' => $this->removeToken($tokens),
            'list' => $this->listTokens($tokens),
        };
    }

    /**
     * @param  Collection<string>  $existingTokens
     */
    protected function addToken(Collection $existingTokens): void
    {
        info('Learn how to create an API token: https://cloud.laravel.com/docs/api/authentication#create-an-api-token');

        $newToken = password(
            label: 'Laravel Cloud API token',
            required: true,
        );

        $this->config->addApiToken($newToken);

        outro('API token saved to '.$this->config->path());
    }

    protected function removeToken(Collection $existingTokens): void
    {
        $token = select(
            label: 'Select a token to remove',
            options: $existingTokens,
        );

        $this->config->removeApiToken($token);

        outro('API token removed');
    }

    protected function listTokens(Collection $existingTokens): void
    {
        // TODO: Refactor once we have proper endpoints for orgs
        $orgs = spin(
            function () use ($existingTokens) {
                return $existingTokens->map(function ($token) {
                    $client = new CloudClient($token);

                    $application = $client->listApplications()->data[0] ?? null;

                    if (! $application || ! $application->organization) {
                        return [
                            'token' => $token,
                            'organization' => 'Unknown',
                        ];
                    }

                    return [
                        'token' => $token,
                        'organization' => $application->organization->name,
                    ];
                });
            },
            'Fetching token details',
        );

        table(
            headers: ['Organization', 'API Token'],
            rows: $orgs->map(fn ($org) => [$org['organization'], $org['token']]),
        );
    }
}
