<?php

namespace App\Commands;

use App\Dto\ObjectStorageBucket;

use function Laravel\Prompts\intro;

class BucketGet extends BaseCommand
{
    protected ?string $jsonDataClass = ObjectStorageBucket::class;

    protected $signature = 'bucket:get {bucket? : The bucket ID or name}';

    protected $description = 'Get object storage bucket details';

    public function handle()
    {
        $this->ensureClient();

        intro('Bucket Details');

        $bucket = $this->resolvers()->objectStorageBucket()->from($this->argument('bucket'));

        $this->outputJsonIfWanted($bucket);

        dataList([
            'ID' => $bucket->id,
            'Name' => $bucket->name,
            'Type' => $bucket->type->value,
            'Status' => $bucket->status->value,
            'Visibility' => $bucket->visibility->value,
            'Jurisdiction' => $bucket->jurisdiction->value,
            'Endpoint' => $bucket->endpoint ?? '—',
            'URL' => $bucket->url ?? '—',
            'Created At' => $bucket->createdAt?->toIso8601String() ?? '—',
        ]);
    }
}
