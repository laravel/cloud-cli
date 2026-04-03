<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class InstanceSizes extends BaseCommand
{
    protected $signature = 'instance:sizes';

    protected $description = 'List available instance sizes';

    public function handle()
    {
        $this->ensureClient();

        intro('Instance Sizes');

        $sizes = spin(
            fn () => $this->client->instances()->sizes(),
            'Fetching instance sizes...',
        );

        $this->outputJsonIfWanted($sizes->toArray());

        if (count($sizes->all()) === 0) {
            warning('No instance sizes found.');

            return self::FAILURE;
        }

        dataTable(
            headers: [
                'Name',
                'Label',
                'CPU Type',
                'Compute Class',
                'CPU Count',
                'Memory (MiB)',
            ],
            rows: collect($sizes->all())->map(fn ($size) => [
                $size->name,
                $size->label,
                $size->cpuType,
                $size->computeClass,
                $size->cpuCount,
                $size->memoryMib,
            ])->toArray(),
        );
    }
}
