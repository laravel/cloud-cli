<?php

namespace App\Commands;

use Carbon\CarbonInterval;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Sleep;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class DatabaseDelete extends BaseCommand
{
    protected $signature = 'database:delete {database? : The database ID or name} {--force : Skip confirmation} {--json : Output as JSON}';

    protected $description = 'Delete a database cluster';

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting Database Cluster');

        if ($this->option('force') && ! $this->argument('database')) {
            warning('Force option provided but no database provided. Will still confirm deletion.');
        }

        $database = $this->resolvers()->databaseCluster()->from($this->argument('database'));
        $dontConfirm = $this->option('force') && $this->argument('database');
        $schemas = spin(
            fn () => $this->client->databaseClusters()->include('schemas')->get($database->id)->schemas,
            'Fetching database cluster schemas...',
        );

        $schemaSuffix = '';

        if (count($schemas) > 0) {
            dataList([
                'Schemas' => collect($schemas)->pluck('name')->implode(PHP_EOL),
            ]);
            $schemaSuffix = ' and schemas';
        }

        if (! $dontConfirm && ! confirm('Delete database cluster'.$schemaSuffix.'?')) {
            error('Cancelled.');

            return self::FAILURE;
        }

        try {
            foreach ($schemas as $schema) {
                $this->loopUntilValid(
                    function ($errors) use ($database, $schema) {
                        if ($errors->messageContains('global', 'please wait')) {
                            info('Waiting a few seconds...');
                            Sleep::for(CarbonInterval::seconds(5));
                        }

                        return spin(
                            fn () => $this->client->databases()->delete($database->id, $schema->id),
                            'Deleting schema '.$schema->name.'...',
                        );
                    },
                );
            }

            $this->loopUntilValid(
                function ($errors) use ($database) {
                    if ($errors->messageContains('global', 'please wait')) {
                        info('Waiting a few seconds...');
                        Sleep::for(CarbonInterval::seconds(5));
                    }

                    return spin(
                        fn () => $this->client->databaseClusters()->delete($database->id),
                        'Deleting database cluster...',
                    );
                },
            );

            $this->outputJsonIfWanted('Database cluster deleted.');

            success('Database cluster deleted.');

            return self::SUCCESS;
        } catch (RequestException $e) {
            error('Failed to delete database cluster: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
