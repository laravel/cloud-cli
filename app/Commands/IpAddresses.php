<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use Laravel\Prompts\Concerns\Colors;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class IpAddresses extends Command
{
    use Colors;
    use HasAClient;

    protected $signature = 'ip:addresses {--json : Output as JSON}';

    protected $description = 'Get Laravel Cloud IP addresses by region';

    public function handle()
    {
        $this->ensureClient();

        intro('Laravel Cloud IP Addresses');

        $addresses = spin(
            fn () => $this->client->getIpAddresses(),
            'Fetching IP addresses...'
        );

        if ($this->option('json')) {
            $this->line(json_encode($addresses, JSON_PRETTY_PRINT));

            return;
        }

        foreach ($addresses as $region => $ips) {
            $this->info("Region: {$region}");

            if (isset($ips['ipv4'])) {
                $this->line('IPv4: '.implode(', ', $ips['ipv4']));
            }

            if (isset($ips['ipv6'])) {
                $this->line('IPv6: '.implode(', ', $ips['ipv6']));
            }

            $this->newLine();
        }
    }
}
