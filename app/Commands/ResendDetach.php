<?php

namespace App\Commands;

use App\Concerns\ManagesResend;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class ResendDetach extends BaseCommand
{
    use ManagesResend;

    protected $signature = 'resend:detach
                            {environment? : The environment ID or name}
                            {--force : Skip confirmation}';

    protected $description = 'Detach Resend from an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Detaching Resend');

        $environment = $this->resolveResendEnvironment(attached: true);

        if (! $environment->resend) {
            $this->failAndExit("Resend is not attached to {$environment->name}.");
        }

        $this->confirmDestructive("Detach Resend from '{$environment->name}'?");

        $this->whenResendIsAvailable(fn () => spin(
            fn () => $this->client->resend()->detach($environment->id),
            'Detaching Resend...',
        ));

        $this->outputJsonIfWanted('Resend detached.');

        success("Resend detached from {$environment->name}.");
    }
}
