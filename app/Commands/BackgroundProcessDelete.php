<?php

namespace App\Commands;

use Saloon\Exceptions\Request\RequestException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class BackgroundProcessDelete extends BaseCommand
{
    protected $signature = 'background-process:delete {process? : The background process ID} {--force : Skip confirmation} {--json : Output as JSON}';

    protected $description = 'Delete a background process';

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting Background Process');

        if ($this->option('force') && ! $this->argument('process')) {
            warning('Force option provided but no process ID provided. Will still confirm deletion.');
        }

        $process = $this->resolvers()->backgroundProcess()->from($this->argument('process'));
        $dontConfirm = $this->option('force') && $this->argument('process');

        if (! $dontConfirm && ! confirm('Delete background process?')) {
            error('Cancelled');

            return self::FAILURE;
        }

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
