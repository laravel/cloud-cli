<?php

namespace App\Commands;

use App\Client\Connector;
use App\Cloud;
use App\ConfigRepository;
use App\Contracts\NoAuthRequired;
use App\Exceptions\CommandExitException;
use App\Support\Stdin;
use Illuminate\Support\Collection;

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
                            {--token= : The API token to add or remove, for non-interactive use (may also be piped to STDIN)}
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
            revealFile($this->config->path());

            outro('Revealed '.$this->config->path());

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

        if (! $this->isInteractive()) {
            $this->failAndExit('Choose an action: --add, --remove, or --list.');
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
        $newToken = $this->tokenFromInput();

        if ($newToken === null) {
            if (! $this->isInteractive()) {
                $this->failAndExit('No API token given. Pass --token=<token> or pipe the token to STDIN.');
            }

            info('Learn how to create an API token: https://cloud.laravel.com/docs/api/authentication#create-an-api-token');

            $newToken = password(
                label: 'Laravel Cloud API token',
                required: true,
            );
        }

        if ($existingTokens->contains($newToken)) {
            $message = 'API token already saved in '.$this->config->path();
        } else {
            $this->config->addApiToken($newToken);

            $message = 'API token saved to '.$this->config->path();
        }

        $this->warnAboutEnvironmentToken();

        $this->writeJsonIfWanted($message);

        outro($message);
    }

    protected function removeToken(Collection $existingTokens): void
    {
        if ($existingTokens->isEmpty()) {
            $this->outputErrorOrThrow('No API tokens to remove.');

            throw new CommandExitException(self::FAILURE);
        }

        $token = $this->tokenFromInput();

        if ($token !== null && ! $existingTokens->contains($token)) {
            $this->failAndExit('Provided API token is not saved in '.$this->config->path().'.');
        }

        if ($token === null) {
            if (! $this->isInteractive()) {
                $this->failAndExit('No API token given. Pass --token=<token> or pipe the token to STDIN.');
            }

            $token = select(
                label: 'Select a token to remove',
                options: $existingTokens,
            );
        }

        $this->config->removeApiToken($token);

        $this->writeJsonIfWanted('API token removed');

        outro('API token removed');
    }

    protected function listTokens(Collection $existingTokens): void
    {
        $sources = $existingTokens
            ->map(fn ($token) => ['token' => $token, 'source' => $this->config->path()])
            ->when(
                Cloud::apiTokenFromEnvironment(),
                fn ($tokens, $envToken) => $tokens->prepend(['token' => $envToken, 'source' => Cloud::API_TOKEN_ENV_VAR]),
            );

        if ($sources->isEmpty()) {
            $this->outputErrorOrThrow('No API tokens found.');

            throw new CommandExitException(self::FAILURE);
        }

        $orgs = spin(
            function () use ($sources) {
                return $sources->map(function ($entry) {
                    $organization = (new Connector($entry['token']))->meta()->organization();

                    return [...$entry, 'organization' => $organization->name];
                });
            },
            'Fetching token details',
        );

        $this->outputJsonIfWanted($orgs->values());

        table(
            headers: ['Organization', 'API Token', 'Source'],
            rows: $orgs->map(fn ($org) => [$org['organization'], $org['token'], $org['source']]),
        );
    }

    protected function tokenFromInput(): ?string
    {
        $token = $this->option('token') ?: app(Stdin::class)->read();

        if ($token === null || trim($token) === '') {
            return null;
        }

        return trim($token);
    }

    /**
     * A saved token is dead weight while the environment variable is set, so say so
     * rather than let the next command look like it ignored the token we just stored.
     */
    protected function warnAboutEnvironmentToken(): void
    {
        if (! Cloud::apiTokenFromEnvironment()) {
            return;
        }

        $message = Cloud::API_TOKEN_ENV_VAR.' is set in your environment and takes precedence over saved tokens. Unset it to use the tokens in '.$this->config->path().'.';

        if ($this->wantsJson()) {
            fwrite(STDERR, json_encode(['warning' => true, 'message' => $message]).PHP_EOL);

            return;
        }

        warning($message);
    }
}
