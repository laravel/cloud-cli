<?php

namespace App\Commands;

use App\Client\Requests\UpdateVanityDomainRequestData;
use App\Dto\Environment;
use App\Prompts\SuffixedTextPrompt;
use Illuminate\Support\Str;
use Saloon\Exceptions\Request\Statuses\TooManyRequestsException;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class EnvironmentVanityDomain extends BaseCommand
{
    protected ?string $jsonDataClass = Environment::class;

    protected $signature = 'environment:vanity-domain
                            {environment? : The environment ID or name}
                            {--name= : The new subdomain, e.g. my-app for my-app.laravel.cloud}
                            {--force : Skip confirmation}';

    protected $description = "Change an environment's Laravel Cloud domain (once every 30 minutes)";

    protected $aliases = ['env:vanity-domain'];

    public function handle()
    {
        $this->ensureClient();

        intro('Changing Vanity Domain');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        [$currentName, $zone] = $this->splitVanityDomain($environment->vanityDomain);

        $this->promptForName($currentName, $zone);

        if ($this->form()->get('name') === $currentName) {
            warning("{$environment->name} already uses {$environment->vanityDomain}.");

            $this->outputJsonIfWanted($environment);

            return self::SUCCESS;
        }

        $newDomain = $this->form()->get('name').$zone;

        if (! $this->option('force')) {
            warning("{$environment->vanityDomain} stops working, and the domain can't be changed again for 30 minutes.");
        }

        $this->confirmDestructive("Change {$environment->vanityDomain} to {$newDomain}?");

        $updatedEnvironment = $this->loopUntilValid(function () use ($environment, $currentName, $zone) {
            $this->promptForName($currentName, $zone);

            return $this->updateVanityDomain($environment);
        });

        $this->outputJsonIfWanted($updatedEnvironment);

        success("{$updatedEnvironment->name} is now at {$updatedEnvironment->url}");
    }

    /**
     * Cloud takes only the first label and adds the rest of the domain itself.
     *
     * @return array{0: string, 1: string}
     */
    protected function splitVanityDomain(string $domain): array
    {
        if ($domain === '') {
            return ['', ''];
        }

        return [Str::before($domain, '.'), '.'.Str::after($domain, '.')];
    }

    protected function promptForName(string $currentName, string $zone): void
    {
        $this->form()->prompt(
            'name',
            fn ($resolver) => $resolver->fromInput(fn (?string $value) => (new SuffixedTextPrompt(
                label: 'Vanity domain',
                suffix: $zone,
                default: $value ?? $currentName,
                required: true,
                validate: fn (string $name) => preg_match('/^[A-Za-z0-9_-]{3,100}$/', $name)
                    ? null
                    : 'Use 3 to 100 letters, numbers, dashes, or underscores.',
            ))->prompt()),
        );
    }

    protected function updateVanityDomain(Environment $environment): Environment
    {
        try {
            return spin(
                fn () => $this->client->environments()->updateVanityDomain(
                    new UpdateVanityDomainRequestData(
                        environmentId: $environment->id,
                        name: $this->form()->get('name'),
                    ),
                ),
                'Changing vanity domain...',
            );
        } catch (TooManyRequestsException $e) {
            $minutes = (int) ceil(((int) $e->getResponse()->header('Retry-After')) / 60);

            $this->failAndExit($minutes > 0
                ? "The vanity domain was changed in the last 30 minutes. Try again in {$minutes} ".Str::plural('minute', $minutes).'.'
                : 'The vanity domain may only be changed once every 30 minutes.');
        }
    }
}
