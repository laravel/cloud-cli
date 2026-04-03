<?php

namespace App\Commands;

use App\Dto\Database;
use App\Dto\DatabaseCluster;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;

class DatabaseOpen extends BaseCommand
{
    protected $signature = 'database:open {cluster? : The database cluster ID or name} {database? : The database ID or name}';

    protected $description = 'Open database locally';

    public function handle()
    {
        $this->ensureClient();

        intro('Open Database Locally');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('cluster'));
        $database = $this->resolvers()->database()->withCluster($cluster)->from($this->argument('database'));

        $url = $this->buildUrl($cluster, $database);

        info($url);

        openUrl($url);

        success("Opened {$database->name}");
    }

    protected function buildUrl(DatabaseCluster $cluster, Database $database): string
    {
        $queryParams = http_build_query([
            'Name' => $cluster->name,
            'Environment' => 'Laravel Cloud',
        ], encoding_type: PHP_QUERY_RFC3986);

        return sprintf(
            '%s://%s:%s@%s:%s/%s?%s',
            $cluster->connection['protocol'],
            urlencode($cluster->connection['username']),
            urlencode($cluster->connection['password']),
            $cluster->connection['hostname'],
            $cluster->connection['port'],
            $database->name,
            $queryParams,
        );
    }
}
