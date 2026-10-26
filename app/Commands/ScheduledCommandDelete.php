<?php

namespace App\Commands;

use Saloon\Exceptions\Request\RequestException;

use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class ScheduledCommandDelete extends BaseCommand
{
    protected $signature = 'scheduled-command:delete
                            {scheduled-command? : The scheduled command ID}
                            {--force : Skip confirmation}';

    protected $description = 'Delete a scheduled command';

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting Scheduled Command');

        $scheduledCommand = $this->resolvers()->scheduledCommand()->from($this->argument('scheduled-command'));

        $this->confirmDestructive('Delete scheduled command?');

        try {
            spin(
                fn () => $this->client->scheduledCommands()->delete($scheduledCommand->id),
                'Deleting scheduled command...',
            );

            $this->outputJsonIfWanted('Scheduled command deleted.');

            success('Scheduled command deleted.');

            return self::SUCCESS;
        } catch (RequestException $e) {
            error('Failed to delete scheduled command: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
