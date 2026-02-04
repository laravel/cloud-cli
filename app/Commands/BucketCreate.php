<?php

namespace App\Commands;

use App\Concerns\DeterminesDefaultRegion;
use App\Concerns\Validates;
use App\Dto\Region;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class BucketCreate extends BaseCommand
{
    use DeterminesDefaultRegion;
    use Validates;

    protected $signature = 'bucket:create
                            {--name= : Bucket name}
                            {--region= : Region}
                            {--visibility= : Visibility (private or public)}
                            {--json : Output as JSON}';

    protected $description = 'Create an object storage bucket';

    public function handle()
    {
        $this->ensureClient();

        intro('Creating Bucket');

        $bucket = $this->loopUntilValid($this->createBucket(...));

        $this->outputJsonIfWanted($bucket);

        success('Bucket created');

        outro("Bucket created: {$bucket->name}");
    }

    protected function createBucket()
    {
        $this->addParam(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => text(
                    label: 'Bucket name',
                    default: $value ?? '',
                    required: true,
                    validate: fn ($v) => strlen($v) < 3 ? 'Name must be at least 3 characters' : null,
                ),
            ),
        );

        $regions = spin(
            fn () => $this->client->meta()->regions(),
            'Fetching regions...',
        );

        $this->addParam(
            'region',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => select(
                    label: 'Region',
                    options: collect($regions)->mapWithKeys(fn (Region $r) => [$r->value => $r->label])->toArray(),
                    default: $value ?? $this->getDefaultRegion(),
                    required: true,
                ))
                ->nonInteractively(fn () => $this->getDefaultRegion()),
        );

        $this->addParam(
            'visibility',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => select(
                    label: 'Visibility',
                    options: ['private' => 'Private', 'public' => 'Public'],
                    default: $value ?? 'private',
                    required: true,
                ))
                ->nonInteractively(fn () => 'private'),
        );

        return spin(
            fn () => $this->client->objectStorageBuckets()->create(
                $this->getParam('name'),
                $this->getParam('region'),
                $this->getParam('visibility'),
            ),
            'Creating bucket...',
        );
    }
}
