<?php

namespace App\Commands;

use App\Client\Connector;
use App\ConfigRepository;
use App\Contracts\NoAuthRequired;
use App\Exceptions\CommandExitException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class AuthToken extends BaseCommand implements NoAuthRequired
{
    protected $signature = 'auth:token
                            {--add : Add a new API token}
                            {--remove : Remove an API token}
                            {--reveal : Reveal the config file in Finder}
                            {--list : List API tokens}';

    protected $description = 'Manage Laravel Cloud API tokens';

    protected ConfigRepository $config;

    public function handle()
    {
        $this->config = app(ConfigRepository::class);
        $tokens = $this->config->apiTokens();

        intro('Laravel Cloud API Tokens');

        if ($this->option('reveal')) {
            Process::run('open '.$this->config->path().' -R');

            outro('Revealed '.$this->config->path().' in Finder');

            return self::SUCCESS;
        }

        if ($this->option('remove')) {
            $this->removeToken($tokens);

            return self::SUCCESS;
        }

        if ($this->option('list')) {
            $this->listTokens($tokens);

            return self::SUCCESS;
        }

        if ($this->option('add')) {
            $this->addToken($tokens);

            return self::SUCCESS;
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
            default => null,
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
        $orgs = spin(
            function () use ($existingTokens) {
                return $existingTokens->map(function ($token) {
                    $client = new Connector($token);

                    $organization = $client->meta()->organization();

                    return [
                        'token' => $token,
                        'organization' => $organization->name,
                    ];
                });
            },
            'Fetching token details',
        );

        if ($orgs->isEmpty()) {
            warning('No API tokens found.');

            throw new CommandExitException(self::FAILURE);
        }

        table(
            headers: ['Organization', 'API Token'],
            rows: $orgs->map(fn ($org) => [$org['organization'], $org['token']]),
        );
    }
}
