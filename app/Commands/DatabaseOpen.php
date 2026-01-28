<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use App\Concerns\RequiresApplication;
use App\Concerns\RequiresEnvironment;
use App\Dto\DatabaseCluster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use RuntimeException;

use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class DatabaseOpen extends BaseCommand
{
    use HasAClient;
    use RequiresApplication;
    use RequiresEnvironment;

    protected $signature = 'database:open
                            {application? : The application ID or name}
                            {environment? : The name of the environment}
                            {database? : The database ID or name}';

    protected $description = 'Open database in TablePlus';

    public function handle()
    {
        $this->ensureClient();

        intro('Opening Database In TablePlus');

        $app = $this->getCloudApplication();
        $environments = spin(
            fn () => $this->client->environments()->list($app->id),
            'Fetching environments...',
        );
        $environment = $this->getEnvironment($environments->collect());

        $environment = spin(
            fn () => $this->client->environments()->get($environment->id),
            'Fetching environment details...',
        );

        $databases = spin(
            fn () => $this->client->databaseClusters()->include('schemas')->list(),
            'Fetching databases...',
        );

        $database = $this->resolveDatabase(
            $databases->collect(),
            $environment,
        );

        if (! $this->canOpenTablePlus()) {
            error('TablePlus is not installed or cannot be opened.');

            exit(1);
        }

        $url = $this->buildTablePlusUrl($database);

        Process::run(['open', $url]);

        outro("Opened {$database->name} in TablePlus");
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

    protected function canOpenTablePlus(): bool
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            return false;
        }

        $result = Process::run('which open');

        if (! $result->successful()) {
            return false;
        }

        $appCheck = Process::run('test -d "/Applications/TablePlus.app" || test -d "$HOME/Applications/TablePlus.app"');

        return $appCheck->successful();
    }

    protected function buildTablePlusUrl(DatabaseCluster $database): string
    {
        $connection = $database->connection ?? [];
        $protocol = $connection['protocol'] ?? null;
        $host = $connection['hostname'] ?? 'localhost';
        $port = $connection['port'] ?? $this->getDefaultPort($type);
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
        ]);

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
