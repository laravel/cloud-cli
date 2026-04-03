<?php

namespace App\Commands;

use App\Concerns\InteractsWithClipbboard;
use Illuminate\Support\Collection;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class IpAddresses extends BaseCommand
{
    use InteractsWithClipbboard;

    protected $signature = 'ip:addresses
                            {--json : Output as JSON}
                            {--copy : Copy to clipboard}
                            {--region= : Filter by region}';

    protected $description = 'Get Laravel Cloud IP addresses by region';

    public function handle()
    {
        intro('Laravel Cloud IP Addresses');

        $this->ensureClient();

        $addresses = spin(
            fn () => collect($this->client->meta()->ipAddresses()),
            'Fetching IP addresses...',
        );

        if ($this->option('region')) {
            $addresses = $addresses->filter(fn ($ips, $region) => str_starts_with($region, strtolower($this->option('region'))));

            if ($addresses->isEmpty()) {
                $this->outputJsonIfWanted([]);

                warning('No IP addresses found for region: '.$this->option('region'));

                return self::FAILURE;
            }
        }

        $addresses = $addresses->sortBy(fn ($ips, $region) => $region);

        $this->outputJsonIfWanted($addresses);

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

        dataTable(
            ['Region', 'IPv4', 'IPv6'],
            $tableData->toArray(),
            actions: [
                Key::ENTER => [
                    fn ($row) => $this->copyToIpsToClipboard(collect([$row['region'] => $row])),
                    'Copy to clipboard',
                ],
            ],
        );

        if ($this->option('copy')) {
            $this->copyToIpsToClipboard($addresses);
        }
    }

    protected function copyToIpsToClipboard(Collection $addresses): void
    {
        if ($addresses->hasSole()) {
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

        $ips = $addresses->first(fn ($ips, $region) => $region === $regionToCopy)[$ipTypeToCopy];

        if (is_string($ips)) {
            $ips = [$ips];
        }

        $text = trim(implode(PHP_EOL, $ips));

        $this->copyToClipboard($text);

        success('IP addresses copied to clipboard');
    }
}
