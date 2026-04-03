<?php

namespace App\Commands;

use Illuminate\Http\Client\RequestException;

use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class BackgroundProcessDelete extends BaseCommand
{
    protected $signature = 'background-process:delete {process? : The background process ID} {--force : Skip confirmation}';

    protected $description = 'Delete a background process';

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting Background Process');

        $process = $this->resolvers()->backgroundProcess()->from($this->argument('process'));

        $this->confirmDestructive('Delete background process?');

        try {
            spin(
                fn () => $this->client->backgroundProcesses()->delete($process->id),
                'Deleting background process...',
            );

            $this->outputJsonIfWanted('Background process deleted.');

            success('Background process deleted.');

            return self::SUCCESS;
        } catch (RequestException $e) {
            error('Failed to delete background process: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
