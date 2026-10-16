<?php

namespace App\Commands;

use App\Dto\ObjectStorageBucket;
use App\Enums\FilesystemJurisdiction;
use App\Enums\FilesystemLocationHint;

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
            'Type' => $bucket->type,
            'Status' => $bucket->status->value,
            'Visibility' => $bucket->visibility->value,
            'Jurisdiction' => FilesystemJurisdiction::tryFrom($bucket->jurisdiction)?->label() ?? $bucket->jurisdiction,
            'Location Hint' => $bucket->locationHint ? (FilesystemLocationHint::tryFrom($bucket->locationHint)?->label() ?? $bucket->locationHint) : '—',
            'Allowed Origins' => implode(', ', $bucket->corsSettings['allowed_origins'] ?? $bucket->allowedOrigins ?? []) ?: '—',
            'Endpoint' => $bucket->endpoint ?? '—',
            'URL' => $bucket->url ?? '—',
            'Created At' => $bucket->createdAt?->toIso8601String() ?? '—',
        ]);
    }
}
