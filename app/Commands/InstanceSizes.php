<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class InstanceSizes extends BaseCommand
{
    protected $signature = 'instance:sizes {--json : Output as JSON}';

    protected $description = 'List available instance sizes';

    public function handle()
    {
        $this->ensureClient();

        intro('Instance Sizes');

        $data = spin(
            fn () => $this->client->instances()->sizes(),
            'Fetching instance sizes...',
        );

        $this->outputJsonIfWanted($data);

        if (empty($data)) {
            warning('No instance sizes found.');

            return self::FAILURE;
        }

        $rows = collect($data)->map(fn ($size) => [
            $size['id'] ?? $size['value'] ?? '-',
            $size['label'] ?? $size['name'] ?? '-',
        ])->toArray();

        dataTable(
            headers: ['Size', 'Label'],
            rows: $rows,
        );
    }
}
