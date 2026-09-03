<?php

namespace App\Commands;

use App\Dto\BucketKey;
use App\Support\SensitiveValues;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class BucketKeyGet extends BaseCommand
{
    protected ?string $jsonDataClass = BucketKey::class;

    protected $signature = 'bucket-key:get
                            {bucket? : The bucket ID or name}
                            {key? : The key ID or name}';

    protected $description = 'Get bucket key details';

    public function handle()
    {
        $this->ensureClient();

        intro('Bucket Key Details');

        $bucket = $this->resolvers()->objectStorageBucket()->from($this->argument('bucket'));
        $key = $this->resolvers()->bucketKey()->from($bucket, $this->argument('key'));

        $key = spin(
            fn () => $this->client->bucketKeys()->get($key->id),
            'Fetching key...',
        );

        $this->outputJsonIfWanted($key);

        SensitiveValues::$reveal = (bool) $this->option('show-sensitive');

        dataList([
            'ID' => $key->id,
            'Name' => $key->name,
            'Permission' => $key->permission,
            'Access Key ID' => $key->accessKeyId ?? '—',
            'Secret Access Key' => SensitiveValues::mask($key->secretAccessKey) ?? '—',
            'Created At' => $key->createdAt?->toIso8601String() ?? '—',
        ]);
    }
}
