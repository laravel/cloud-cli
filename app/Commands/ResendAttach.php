<?php

namespace App\Commands;

use App\Client\Requests\AttachResendRequestData;
use App\Concerns\ManagesResend;
use App\Dto\Environment;
use App\Dto\ResendSendingKey;
use Illuminate\Support\Composer;
use Laravel\Prompts\Support\Logger;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\task;
use function Laravel\Prompts\warning;

class ResendAttach extends BaseCommand
{
    use ManagesResend;

    /**
     * Cloud sets these from the Resend integration and removes any copies from the environment's own variables.
     */
    protected const INJECTED_VARIABLES = ['RESEND_API_KEY', 'MAIL_MAILER', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME'];

    protected const RESEND_PACKAGE = 'resend/resend-php';

    protected ?string $jsonDataClass = Environment::class;

    protected $signature = 'resend:attach
                            {environment? : The environment ID or name}
                            {--from-address= : The sender address, on a verified sending domain}
                            {--from-name= : The sender name (defaults to the application name)}
                            {--key-name= : A name for the new sending key}
                            {--reuse-key-id= : Reuse this sending key instead of creating one (see resend:keys)}';

    protected $description = 'Attach Resend to an environment so it can send email';

    public function handle()
    {
        $this->ensureClient();

        intro('Attaching Resend');

        // The application name is the default sender name.
        $environment = $this->resolveResendEnvironment(attached: false, includes: ['application']);

        if ($environment->resend) {
            $this->failAndExit("Resend is already attached to {$environment->name}, sending as {$environment->resend->sender()}. Change the sender with `cloud resend:update`.");
        }

        $domains = $this->verifiedSendingDomains();

        $updatedEnvironment = $this->loopUntilValid(fn () => $this->attach($environment, $domains));

        $composer = $this->composerMissingResendPackage();

        if ($composer && ! $this->isInteractive()) {
            $this->outputWarning('Laravel needs the '.self::RESEND_PACKAGE.' package to send through Resend. Run `composer require '.self::RESEND_PACKAGE.'`, then deploy.');
        }

        $this->outputJsonIfWanted($updatedEnvironment);

        success("Resend attached to {$updatedEnvironment->name}, sending as {$updatedEnvironment->resend?->sender()}.");

        $replaced = array_intersect(self::INJECTED_VARIABLES, array_column($environment->environmentVariables, 'key'));

        if ($replaced !== []) {
            warning('Resend now sets '.implode(', ', self::INJECTED_VARIABLES).', so these were removed from the environment variables: '.implode(', ', $replaced).'.');
        }

        if ($composer) {
            $this->offerToInstallResendPackage($composer);
        }
    }

    /**
     * Only the project in the current directory can be checked, and only when it's a Composer project.
     */
    protected function composerMissingResendPackage(): ?Composer
    {
        $path = getcwd();

        if ($path === false || ! file_exists($path.'/composer.json')) {
            return null;
        }

        $composer = app(Composer::class)->setWorkingPath($path);

        return $composer->hasPackage(self::RESEND_PACKAGE) ? null : $composer;
    }

    protected function offerToInstallResendPackage(Composer $composer): void
    {
        warning('Laravel needs the '.self::RESEND_PACKAGE.' package to send through Resend.');

        if (! confirm('Install '.self::RESEND_PACKAGE.' now?')) {
            return;
        }

        $installed = task(
            label: 'Installing '.self::RESEND_PACKAGE,
            callback: fn (Logger $log) => $composer->requirePackages(
                [self::RESEND_PACKAGE],
                output: function (string $type, string $output) use ($log) {
                    foreach (preg_split('/\R/', trim($output)) as $line) {
                        if ($line !== '') {
                            $log->line($line);
                        }
                    }
                },
            ),
        );

        if ($installed) {
            success('Installed '.self::RESEND_PACKAGE.'. Commit and deploy to start sending.');
        } else {
            error('Could not install '.self::RESEND_PACKAGE.'. Run `composer require '.self::RESEND_PACKAGE.'` yourself.');
        }
    }

    /**
     * @param  list<string>  $domains
     */
    protected function attach(Environment $environment, array $domains): Environment
    {
        $this->promptForSender($domains, null, $environment->application->name ?? $environment->name);

        $this->form()->prompt(
            'reuse_key_id',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => $this->selectSendingKey($environment, $value))
                // Creating a new key is the default, so an absent option is a real answer.
                ->nonInteractively(fn () => ''),
        );

        $reuseKeyId = $this->form()->get('reuse_key_id') ?: null;

        return spin(
            fn () => $this->client->resend()->attach(
                new AttachResendRequestData(
                    environmentId: $environment->id,
                    fromAddress: $this->form()->get('from_address'),
                    fromName: $this->form()->get('from_name'),
                    keyName: $reuseKeyId === null ? $this->option('key-name') : null,
                    reuseKeyId: $reuseKeyId,
                ),
            ),
            'Attaching Resend...',
        );
    }

    protected function selectSendingKey(Environment $environment, ?string $value): string
    {
        $keys = spin(
            fn () => $this->client->resend()->reusableKeys($environment->id),
            'Fetching sending keys...',
        );

        if ($keys === []) {
            return '';
        }

        $options = collect($keys)
            ->mapWithKeys(fn (ResendSendingKey $key) => [
                $key->id => "Reuse {$key->name} ({$key->environmentCount} ".str('environment')->plural($key->environmentCount).')',
            ])
            ->prepend('Create a new sending key', '')
            ->all();

        return select(
            label: 'Sending key',
            options: $options,
            default: $value ?? '',
        );
    }
}
