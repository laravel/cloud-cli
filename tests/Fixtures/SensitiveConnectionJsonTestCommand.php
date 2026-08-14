<?php

namespace Tests\Fixtures;

use App\Commands\BaseCommand;
use App\Dto\BucketKey;
use App\Dto\Cache;
use App\Dto\DatabaseCluster;
use App\Dto\WebsocketApplication;

class SensitiveConnectionJsonTestCommand extends BaseCommand
{
    protected $signature = 'test:sensitive-connection-json';

    public function handle(): void
    {
        $cache = Cache::from([
            'id' => 'cache-1',
            'type' => 'valkey',
            'name' => 'main',
            'status' => 'running',
            'region' => 'us-east-1',
            'size' => 'small',
            'autoUpgradeEnabled' => false,
            'isPublic' => false,
            'connection' => [
                'hostname' => 'cache.example.com',
                'port' => 6379,
                'protocol' => 'redis',
                'username' => 'default',
                'password' => 'cache-password',
                'url' => 'rediss://default:cache-password@cache.example.com:6379',
            ],
        ]);

        $cluster = DatabaseCluster::from([
            'id' => 'db-1',
            'name' => 'primary',
            'type' => 'postgres',
            'status' => 'running',
            'region' => 'us-east-1',
            'config' => [],
            'connection' => [
                'host' => 'db.example.com',
                'port' => 5432,
                'username' => 'forge',
                'password' => 'db-password',
                'dsn' => 'postgres://forge:db-password@db.example.com:5432/forge',
            ],
        ]);

        $bucketKey = BucketKey::from([
            'id' => 'key-1',
            'name' => 'deploys',
            'permission' => 'read_write',
            'accessKeyId' => 'AKIAEXAMPLE',
            'secretAccessKey' => 'bucket-secret',
        ]);

        $websocketApplication = WebsocketApplication::from([
            'id' => 'ws-1',
            'name' => 'app',
            'appId' => '12345',
            'allowedOrigins' => ['*'],
            'pingInterval' => 30,
            'activityTimeout' => 30,
            'maxMessageSize' => 1024,
            'maxConnections' => 100,
            'key' => 'ws-key',
            'secret' => 'ws-secret',
        ]);

        $this->outputJsonIfWanted([
            'cache' => $cache,
            'databaseCluster' => $cluster,
            'bucketKey' => $bucketKey,
            'websocketApplication' => $websocketApplication,
        ]);
    }
}
