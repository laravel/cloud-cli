<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use Illuminate\Http\Client\RequestException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class BackgroundProcessDelete extends BaseCommand
{
    use HasAClient;

    protected $signature = 'background-process:delete {process : The background process ID} {--force : Skip confirmation}';

    protected $description = 'Delete a background process';

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting Background Process');

        $processId = $this->argument('process');

        if (! $this->option('force')) {
            $process = spin(
                fn () => $this->client->getBackgroundProcess($processId),
                'Fetching background process...',
            );

            if (! confirm("Delete background process '{$process->command}'?")) {
                info('Cancelled.');

                return;
            }
        }

        try {
            spin(
                fn () => $this->client->deleteBackgroundProcess($processId),
                'Deleting background process...',
            );

            success('Background process deleted.');
        } catch (RequestException $e) {
            error('Failed to delete background process: '.$e->getMessage());

            return 1;
        }
    }
}
