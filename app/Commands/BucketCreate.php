<?php

namespace App\Commands;

use App\Client\Requests\CreateObjectStorageBucketRequestData;
use App\Concerns\DefinesBucketCorsSettings;
use App\Concerns\DeterminesDefaultRegion;
use App\Dto\ObjectStorageBucket;
use App\Enums\FilesystemJurisdiction;
use App\Enums\FilesystemLocationHint;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class BucketCreate extends BaseCommand
{
    protected ?string $jsonDataClass = ObjectStorageBucket::class;

    use DefinesBucketCorsSettings;
    use DeterminesDefaultRegion;

    protected $signature = 'bucket:create
                            {--name= : Bucket name}
                            {--region= : Region}
                            {--visibility= : Visibility (private or public)}
                            {--jurisdiction= : Jurisdiction (default, eu, or us)}
                            {--location-hint= : Where the bucket data is placed (automatic, wnam, enam, weur, eeur, apac, or oc; only automatic with an eu or us jurisdiction)}
                            {--key-name= : Key name (required for S3 compatible buckets)}
                            {--key-permission= : Key permission (read_only or read_write)}
                            {--allowed-origins= : CORS allowed origins (comma-separated list)}
                            {--allowed-methods= : CORS allowed methods (comma-separated list of GET, POST, PUT, DELETE, HEAD)}
                            {--allowed-headers= : CORS allowed headers (comma-separated list)}
                            {--expose-headers= : CORS headers exposed to the browser (comma-separated list)}
                            {--max-age-seconds= : CORS preflight cache duration in seconds}';

    protected $description = 'Create an object storage bucket';

    public function handle()
    {
        $this->ensureClient();

        intro('Creating Bucket');

        $bucket = $this->loopUntilValid($this->createBucket(...));

        $this->outputJsonIfWanted($bucket);

        success('Bucket created');
    }

    protected function createBucket()
    {
        $this->form()->prompt(
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

        $this->form()->prompt(
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

        $this->form()->prompt(
            'jurisdiction',
            fn ($resolver) => $resolver
                ->fromInput(fn ($value) => select(
                    label: 'Jurisdiction',
                    options: collect(FilesystemJurisdiction::cases())
                        ->mapWithKeys(fn (FilesystemJurisdiction $jurisdiction) => [$jurisdiction->value => $jurisdiction->label()])
                        ->all(),
                    default: $value ?? FilesystemJurisdiction::DEFAULT->value,
                    hint: 'Where the bucket stores its data.',
                ))
                ->nonInteractively(fn () => 'default'),
        );

        if ($this->form()->get('jurisdiction') === FilesystemJurisdiction::DEFAULT->value) {
            $this->form()->prompt(
                'location_hint',
                fn ($resolver) => $resolver
                    ->fromInput(fn ($value) => select(
                        label: 'Location hint',
                        options: collect(FilesystemLocationHint::cases())
                            ->mapWithKeys(fn (FilesystemLocationHint $hint) => [$hint->value => $hint->label()])
                            ->all(),
                        default: $value ?? FilesystemLocationHint::AUTOMATIC->value,
                        hint: 'Where the bucket\'s data is placed.',
                    ))
                    ->nonInteractively(fn () => FilesystemLocationHint::AUTOMATIC->value),
            );
        } else {
            // A jurisdiction only allows "automatic", so don't ask; pass along an explicit option for the API to judge.
            $this->form()->define('location_hint', fn ($resolver) => $resolver);
        }

        $this->form()->prompt(
            'key_name',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Key name',
                    default: $value ?? '',
                    required: true,
                    validate: fn ($v) => match (true) {
                        strlen($v) < 3 => 'Name must be at least 3 characters',
                        strlen($v) > 40 => 'Name must be less than 40 characters',
                        default => null,
                    },
                ),
            ),
        );

        $this->form()->prompt(
            'key_permission',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => select(
                    label: 'Key permission',
                    options: ['read_only' => 'Read only', 'read_write' => 'Read and write'],
                    default: $value ?? 'read_write',
                    required: true,
                ),
            ),
        );

        $this->defineCorsFields();

        $this->form()->prompt('cors_settings.allowed_origins');

        // The rest of the CORS settings are option-only on create, unless the API rejected one of them.
        foreach (['cors_settings.allowed_methods', 'cors_settings.allowed_headers', 'cors_settings.expose_headers', 'cors_settings.max_age_seconds'] as $key) {
            if ($this->errors?->has($key)) {
                $this->form()->prompt($key);
            }
        }

        return spin(
            fn () => $this->client->objectStorageBuckets()->create(
                new CreateObjectStorageBucketRequestData(
                    name: $this->form()->get('name'),
                    visibility: $this->form()->get('visibility'),
                    jurisdiction: $this->form()->get('jurisdiction'),
                    keyName: $this->form()->get('key_name'),
                    keyPermission: $this->form()->get('key_permission'),
                    locationHint: $this->form()->get('location_hint'),
                    corsSettings: $this->corsSettingsFromForm(emptyClears: false),
                ),
            ),
            'Creating bucket...',
        );
    }
}
