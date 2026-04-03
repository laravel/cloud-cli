<?php

namespace App\Commands;

use App\Client\Requests\UpdateCacheRequestData;
use App\Dto\Cache;
use App\Exceptions\CommandExitException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class CacheUpdate extends BaseCommand
{
    protected ?string $jsonDataClass = Cache::class;

    protected $signature = 'cache:update
                            {cache? : The cache ID or name}
                            {--name= : Cache name}
                            {--size= : Cache size}
                            {--auto-upgrade-enabled= : Enable auto upgrade}
                            {--is-public= : Whether cache is public}
                            {--force : Force update without confirmation}';

    protected $description = 'Update a cache';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Cache');

        $cache = $this->resolvers()->cache()->from($this->argument('cache'));

        $this->defineFields($cache);

        foreach ($this->form()->filled() as $value) {
            $this->reportChange(
                $value->label(),
                $value->previousValue(),
                $value->value(),
            );
        }

        $updatedCache = $this->runUpdate(
            fn () => $this->updateCache($cache),
            fn () => $this->collectDataAndUpdate($cache),
        );

        $this->outputJsonIfWanted($updatedCache);

        success("Cache updated: {$updatedCache->name}");
    }

    protected function updateCache(Cache $cache): Cache
    {
        $autoUpgradeEnabled = $this->form()->get('auto_upgrade_enabled');
        $isPublic = $this->form()->get('is_public');

        spin(
            fn () => $this->client->caches()->update(
                new UpdateCacheRequestData(
                    cacheId: $cache->id,
                    name: $this->form()->get('name'),
                    size: $this->form()->get('size'),
                    autoUpgradeEnabled: $autoUpgradeEnabled !== null ? filter_var($autoUpgradeEnabled, FILTER_VALIDATE_BOOLEAN) : null,
                    isPublic: $isPublic !== null ? filter_var($isPublic, FILTER_VALIDATE_BOOLEAN) : null,
                ),
            ),
            'Updating cache...',
        );

        return $this->client->caches()->get($cache->id);
    }

    protected function defineFields(Cache $cache): void
    {
        $this->form()->define(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Name',
                    required: true,
                    default: $value ?? $cache->name,
                    validate: fn ($v) => strlen($v) < 3 ? 'Name must be at least 3 characters' : null,
                ),
            ),
        )->setPreviousValue($cache->name);

        $this->form()->define(
            'size',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => select(
                    label: 'Size',
                    options: [
                        '250mb' => '250 MB',
                        '1gb' => '1 GB',
                        '2.5gb' => '2.5 GB',
                        '5gb' => '5 GB',
                        '12gb' => '12 GB',
                        '50gb' => '50 GB',
                        '100gb' => '100 GB',
                        '500gb' => '500 GB',
                    ],
                    default: $value ?? $cache->size,
                ),
            ),
        )->setPreviousValue($cache->size);

        $this->form()->define(
            'auto_upgrade_enabled',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => confirm(
                    label: 'Auto upgrade enabled?',
                    default: (bool) ($value ?? $cache->autoUpgradeEnabled),
                ),
            ),
            'auto-upgrade-enabled',
        )->setPreviousValue($cache->autoUpgradeEnabled ? 'true' : 'false');

        $this->form()->define(
            'is_public',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => confirm(
                    label: 'Public?',
                    default: (bool) ($value ?? $cache->isPublic),
                ),
            ),
            'is-public',
        )->setPreviousValue($cache->isPublic ? 'true' : 'false');
    }

    protected function collectDataAndUpdate(Cache $cache): Cache
    {
        $selection = multiselect(
            label: 'What do you want to update?',
            options: collect($this->form()->defined())->mapWithKeys(fn ($field, $key) => [
                $field->key => $field->label(),
            ])->toArray(),
        );

        if (empty($selection)) {
            $this->outputErrorOrThrow('No fields to update. Select at least one option.');

            throw new CommandExitException(self::FAILURE);
        }

        foreach ($selection as $optionName) {
            $this->form()->prompt($optionName);
        }

        return $this->updateCache($cache);
    }
}
