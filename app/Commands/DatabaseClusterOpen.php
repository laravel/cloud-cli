<?php

namespace App\Commands;

use App\Dto\DatabaseCluster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use RuntimeException;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;

class DatabaseClusterOpen extends BaseCommand
{
    protected $signature = 'database-cluster:open {database? : The database ID or name}';

    protected $description = 'Open database in TablePlus';

    public function handle()
    {
        $this->ensureClient();

        intro('Open Database Locally');

        $database = $this->resolvers()->databaseCluster()->from($this->argument('database'));

        $url = $this->buildUrl($database);

        info($url);

        Process::run(['open', $url]);

        outro("Opened {$database->name} ");
    }

    protected function resolveDatabase(Collection $databases, $environment): DatabaseCluster
    {
        if ($this->argument('database')) {
            $identifier = $this->argument('database');
            $database = $databases->firstWhere('id', $identifier)
                ?? $databases->firstWhere('name', $identifier);

            if (! $database) {
                throw new RuntimeException("Database '{$identifier}' not found.");
            }

            return $database;
        }

        $contextualDatabase = $this->findContextualDatabase($databases, $environment);

        if ($contextualDatabase) {
            return $contextualDatabase;
        }

        if ($databases->isEmpty()) {
            throw new RuntimeException('No databases found.');
        }

        if ($databases->hasSole()) {
            return $databases->first();
        }

        $selectedDatabase = select(
            label: 'Database',
            options: $databases->mapWithKeys(fn ($database) => [$database->id => $database->name]),
        );

        return $databases->firstWhere('id', $selectedDatabase);
    }

    protected function findContextualDatabase(Collection $databases, $environment): ?DatabaseCluster
    {
        $envVars = $environment->environmentVariables ?? [];

        $dbHost = $envVars['DB_HOST'] ?? null;
        $dbDatabase = $envVars['DB_DATABASE'] ?? null;

        if (! $dbHost || ! $dbDatabase) {
            return null;
        }

        foreach ($databases as $database) {
            $connection = $database->connection ?? [];
            $connectionHost = $connection['host'] ?? null;
            $connectionDatabase = $connection['database'] ?? null;

            if ($connectionHost && $connectionDatabase) {
                if ($this->hostsMatch($dbHost, $connectionHost) && $connectionDatabase === $dbDatabase) {
                    return $database;
                }
            }

            foreach ($database->schemas as $schema) {
                if ($schema->name === $dbDatabase) {
                    return $database;
                }
            }
        }

        return null;
    }

    protected function hostsMatch(string $host1, string $host2): bool
    {
        $normalize = fn ($host) => str_replace(['http://', 'https://'], '', trim($host));

        return $normalize($host1) === $normalize($host2);
    }

    protected function buildUrl(DatabaseCluster $database): string
    {
        $connection = $database->connection ?? [];
        $protocol = $connection['protocol'] ?? null;
        $host = $connection['hostname'] ?? 'localhost';
        $port = $connection['port'] ?? $this->getDefaultPort($database->type);
        $user = $connection['username'] ?? $connection['user'] ?? 'root';
        $password = $connection['password'] ?? '';

        $databaseName = count($database->schemas) === 1 ? $database->schemas[0]->name : select(
            label: 'Database',
            options: collect($database->schemas)->mapWithKeys(fn ($schema) => [$schema->name => $schema->name]),
            required: true,
        );

        $url = "{$protocol}://";

        if ($user) {
            $url .= urlencode($user);

            if ($password) {
                $url .= ':'.urlencode($password);
            }

            $url .= '@';
        }

        $url .= $host;

        if ($port) {
            $url .= ':'.$port;
        }

        if ($databaseName) {
            $url .= '/'.$databaseName;
        }

        $queryParams = http_build_query([
            'Name' => $database->name,
            'Environment' => 'Laravel Cloud',
        ], encoding_type: PHP_QUERY_RFC3986);

        return $url.'?'.$queryParams;
    }

    protected function getProtocolForType(string $type): string
    {
        return match (strtolower($type)) {
            'mysql', 'mariadb' => 'mysql',
            'postgresql', 'postgres' => 'postgresql',
            'redis' => 'redis',
            'mongodb' => 'mongodb',
            default => 'mysql',
        };
    }

    protected function getDefaultPort(string $type): ?int
    {
        return match (strtolower($type)) {
            'mysql', 'mariadb' => 3306,
            'postgresql', 'postgres' => 5432,
            'redis' => 6379,
            'mongodb' => 27017,
            default => null,
        };
    }
}
