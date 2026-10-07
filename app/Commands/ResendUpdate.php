<?php

namespace App\Commands;

use App\Client\Requests\UpdateResendRequestData;
use App\Concerns\ManagesResend;
use App\Dto\Environment;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class ResendUpdate extends BaseCommand
{
    use ManagesResend;

    protected ?string $jsonDataClass = Environment::class;

    protected $signature = 'resend:update
                            {environment? : The environment ID or name}
                            {--from-address= : The sender address, on a verified sending domain}
                            {--from-name= : The sender name}';

    protected $description = "Change the sender for an environment's Resend integration";

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Resend');

        $environment = $this->resolveResendEnvironment(attached: true);

        if (! $environment->resend) {
            $this->failAndExit("Resend is not attached to {$environment->name}. Attach it with `cloud resend:attach`.");
        }

        $domains = $this->verifiedSendingDomains();

        $updatedEnvironment = $this->loopUntilValid(function () use ($environment, $domains) {
            $this->promptForSender($domains, $environment->resend->fromAddress, $environment->resend->fromName);

            return spin(
                fn () => $this->client->resend()->update(
                    new UpdateResendRequestData(
                        environmentId: $environment->id,
                        fromAddress: $this->form()->get('from_address'),
                        fromName: $this->form()->get('from_name'),
                    ),
                ),
                'Updating Resend...',
            );
        });

        $this->outputJsonIfWanted($updatedEnvironment);

        success("Resend for {$updatedEnvironment->name} now sends as {$updatedEnvironment->resend?->sender()}.");
    }
}
