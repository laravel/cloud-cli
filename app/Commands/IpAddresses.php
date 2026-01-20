<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use Illuminate\Support\Facades\Process;
use Laravel\Prompts\Concerns\Colors;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class IpAddresses extends Command
{
    use Colors;
    use HasAClient;

    protected $signature = 'ip:addresses'
        .'{--json : Output as JSON}'
        .'{--copy : Copy to clipboard}'
        .'{--region= : Filter by region}';

    protected $description = 'Get Laravel Cloud IP addresses by region';

    public function handle()
    {
        intro('Laravel Cloud IP Addresses');

        $this->ensureClient();

        $addresses = spin(
            fn () => collect($this->client->getIpAddresses()),
            'Fetching IP addresses...'
        );

        if ($this->option('region')) {
            $addresses = $addresses->filter(fn ($ips, $region) => str_starts_with($region, strtolower($this->option('region'))));

            if ($addresses->isEmpty()) {
                warning('No IP addresses found for region: '.$this->option('region'));

                return;
            }
        }

        $addresses = $addresses->sortBy(fn ($ips, $region) => $region);

        if ($this->option('json')) {
            $this->line(json_encode($addresses, JSON_PRETTY_PRINT));

            return;
        }

        $tableData = $addresses->map(fn ($ips, $region) => [
            'region' => $region,
            'ipv4' => implode(PHP_EOL, $ips['ipv4']).PHP_EOL,
            'ipv6' => implode(PHP_EOL, $ips['ipv6']).PHP_EOL,
        ]);

        $lastAddress = $tableData->last();

        $lastAddress['ipv4'] = rtrim($lastAddress['ipv4']);
        $lastAddress['ipv6'] = rtrim($lastAddress['ipv6']);

        $tableData->pop();
        $tableData->push($lastAddress);

        table(
            ['Region', 'IPv4', 'IPv6'],
            $tableData->toArray(),
        );

        if ($this->option('copy')) {
            if ($addresses->containsOneItem()) {
                $regionToCopy = $addresses->keys()->first();
            } else {
                $regionToCopy = select(
                    label: 'Region to copy',
                    options: $addresses->keys()->toArray(),
                );
            }

            $ipTypeToCopy = select(
                label: 'Select IP type to copy',
                options: ['ipv4', 'ipv6'],
            );

            $this->copyToClipboard(
                $tableData->where('region', $regionToCopy)->pluck($ipTypeToCopy)->implode(PHP_EOL),
            );
        }
    }

    protected function copyToClipboard(string $text): void
    {
        Process::run(sprintf('echo "%s" | pbcopy', trim($text)));

        success('IP addresses copied to clipboard');
    }
}
