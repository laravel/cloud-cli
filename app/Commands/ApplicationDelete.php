<?php

namespace App\Commands;

use Illuminate\Http\Client\RequestException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class ApplicationDelete extends BaseCommand
{
    protected $signature = 'application:delete
                            {application? : The application ID or name}
                            {--force : Skip confirmation}
                            {--json : Output as JSON}';

    protected $description = 'Delete an application';

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting Application');

        $application = $this->resolvers()->application()->from($this->argument('application'));

        if (! $this->option('force') && ! confirm("Delete application '{$application->name}'?", default: false)) {
            error('Cancelled');

            return self::FAILURE;
        }

        try {
            spin(
                fn () => $this->client->applications()->delete($application->id),
                'Deleting application...',
            );

            $this->outputJsonIfWanted('Application deleted.');

            success('Application deleted.');
        } catch (RequestException $e) {
            error('Failed to delete application: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
