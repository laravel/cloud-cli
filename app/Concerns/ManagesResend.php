<?php

namespace App\Concerns;

use App\Commands\BaseCommand;
use App\Dto\Environment;
use App\Dto\ResendDomain;
use App\Exceptions\CommandExitException;
use App\Prompts\SuffixedTextPrompt;
use Illuminate\Support\Str;
use Saloon\Exceptions\Request\Statuses\NotFoundException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

trait ManagesResend
{
    /**
     * With no environment given, only the app's environments in the right Resend state are worth
     * offering: picking one by local config or git branch often lands on one this command can't act on.
     *
     * @param  list<string>  $includes
     */
    protected function resolveResendEnvironment(bool $attached, array $includes = []): Environment
    {
        $resolver = $this->resolvers()->environment()->include(...$includes);

        if ($identifier = $this->argument('environment')) {
            return $resolver->from($identifier);
        }

        $application = $this->resolvers()->application()->resolve();

        $candidates = collect($application->environments)
            ->filter(fn (Environment $environment) => ($environment->resend !== null) === $attached)
            ->values();

        if ($candidates->isEmpty()) {
            $this->failAndExit($attached
                ? "No environment in {$application->name} has Resend attached."
                : "Every environment in {$application->name} already has Resend attached.");
        }

        $resolver->withApplication($application);

        if ($candidates->hasSole()) {
            return $resolver->from($candidates->first()->id);
        }

        $options = $candidates->mapWithKeys(fn (Environment $environment) => [$environment->id => $environment->name])->all();

        if (! $this->isInteractive()) {
            $this->failAndExit('More than one environment '.($attached ? 'has' : 'is without').' Resend. Provide an environment ID or name: '.implode(', ', $options).'.');
        }

        $selected = select(
            label: 'Environment',
            options: $options,
            info: fn ($id) => $id,
        );

        // The select already shows which environment was picked.
        return $resolver->shouldDisplayResolved(false)->from($selected);
    }

    /**
     * Cloud answers 404 on every Resend endpoint when the organization doesn't have the integration.
     */
    protected function whenResendIsAvailable(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (NotFoundException) {
            $this->failAndExit('Resend is not available for this organization.');
        }
    }

    /**
     * @return list<string>
     */
    protected function verifiedSendingDomains(): array
    {
        $domains = $this->whenResendIsAvailable(fn () => spin(
            fn () => collect($this->client->resend()->domains(status: 'verified')->collect())
                ->map(fn (ResendDomain $domain) => Str::lower($domain->name))
                ->values()
                ->all(),
            'Fetching sending domains...',
        ));

        if ($domains === []) {
            $this->failWithoutSendingDomains();
        }

        return $domains;
    }

    /**
     * Connecting Resend and verifying domains happen in the dashboard; the API can't do either.
     */
    protected function failWithoutSendingDomains(): never
    {
        $url = $this->client->meta()->organization()->integrationsUrl();

        $message = "No verified Resend sending domains found. Connect Resend and verify a domain at {$url}";

        if ($this->isInteractive()) {
            error($message);

            if (confirm('Open integration settings in your browser?')) {
                openUrl($url);
            }

            throw new CommandExitException(BaseCommand::FAILURE);
        }

        $this->failAndExit($message);
    }

    /**
     * @param  list<string>  $domains
     */
    protected function promptForSender(array $domains, ?string $currentAddress, string $currentName): void
    {
        $this->form()->prompt(
            'from_address',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => $this->promptForSenderAddress($domains, $value ?? $currentAddress))
                ->nonInteractively(fn () => $currentAddress),
        );

        $this->form()->prompt(
            'from_name',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => text(
                    label: 'Sender name',
                    default: $value ?? $currentName,
                    required: true,
                ))
                ->nonInteractively(fn () => $currentName),
        );
    }

    /**
     * Picking the domain first means the address can only ever land on a verified one.
     *
     * @param  list<string>  $domains
     */
    protected function promptForSenderAddress(array $domains, ?string $previous): string
    {
        $previousLocalPart = $previous !== null ? Str::before($previous, '@') : '';
        $previousDomain = $previous !== null ? Str::lower(Str::after($previous, '@')) : null;

        $domain = count($domains) === 1 ? $domains[0] : select(
            label: 'Sending domain',
            options: array_combine($domains, $domains),
            default: in_array($previousDomain, $domains, true) ? $previousDomain : $domains[0],
        );

        $localPart = (new SuffixedTextPrompt(
            label: 'Sender address',
            suffix: "@{$domain}",
            placeholder: 'hello',
            default: $previousLocalPart,
            required: true,
            validate: fn (string $value) => filter_var("{$value}@{$domain}", FILTER_VALIDATE_EMAIL)
                ? null
                : 'Enter a valid address.',
        ))->prompt();

        return "{$localPart}@{$domain}";
    }
}
