<?php

namespace App\Commands;

use App\Client\Requests\UpdateCacheRequestData;
use App\Dto\Cache;
use App\Support\UpdateFields;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class CacheUpdate extends BaseCommand
{
    protected $signature = 'cache:update
                            {cache? : The cache ID or name}
                            {--name= : Cache name}
                            {--size= : Cache size}
                            {--auto-upgrade-enabled= : Enable auto upgrade}
                            {--is-public= : Whether cache is public}
                            {--force : Force update without confirmation}
                            {--json : Output as JSON}';

    protected $description = 'Update a cache';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Cache');

        $cache = $this->resolvers()->cache()->from($this->argument('cache'));

        $fields = $this->getFieldDefinitions($cache);

        $data = [];

        foreach ($fields as $optionName => $field) {
            if ($this->option($optionName)) {
                $value = $this->option($optionName);
                $data[$field['key']] = $this->coerceValue($field['key'], $value);

                $this->reportChange(
                    $field['label'],
                    (string) $field['current'],
                    (string) $value,
                );
            }
        }

        $updatedCache = $this->resolveUpdatedCache($cache, $fields, $data);

        $this->outputJsonIfWanted($updatedCache);

        success('Cache updated');

        outro("Cache updated: {$updatedCache->name}");
    }

    protected function resolveUpdatedCache(Cache $cache, array $fields, array $data): Cache
    {
        if (! $this->isInteractive()) {
            if (empty($data)) {
                $this->outputErrorOrThrow('No fields to update. Provide at least one option.');

                exit(self::FAILURE);
            }

            return $this->updateCache($cache, $data);
        }

        if (empty($data)) {
            return $this->collectDataAndUpdate($fields, $cache);
        }

        if (! $this->shouldRunUpdateFromOptions()) {
            error('Update cancelled');

            exit(self::FAILURE);
        }

        return $this->updateCache($cache, $data);
    }

    protected function updateCache(Cache $cache, array $data): Cache
    {
        spin(
            fn () => $this->client->caches()->update(new UpdateCacheRequestData(
                cacheId: $cache->id,
                name: isset($data['name']) ? (string) $data['name'] : null,
                size: isset($data['size']) ? (string) $data['size'] : null,
                autoUpgradeEnabled: array_key_exists('auto_upgrade_enabled', $data) ? $this->coerceValue('auto_upgrade_enabled', $data['auto_upgrade_enabled']) : null,
                isPublic: array_key_exists('is_public', $data) ? $this->coerceValue('is_public', $data['is_public']) : null,
            )),
            'Updating cache...',
        );

        return $this->client->caches()->get($cache->id);
    }

    protected function shouldRunUpdateFromOptions(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        return confirm('Update the cache?');
    }

    protected function getFieldDefinitions(Cache $cache): array
    {
        $fields = new UpdateFields;

        $fields->add('name', fn ($value) => text(
            label: 'Name',
            default: $value ?? $cache->name,
            required: true,
            validate: fn ($v) => strlen($v) < 3 ? 'Name must be at least 3 characters' : null,
        ))->currentValue($cache->name)->dataKey('name');
        $fields->add('size', fn ($value) => select(
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
        ))->currentValue($cache->size)->dataKey('size');
        $fields->add('auto-upgrade-enabled', fn ($value) => confirm(
            label: 'Auto upgrade enabled?',
            default: (bool) ($value ?? $cache->autoUpgradeEnabled),
        ))->currentValue($cache->autoUpgradeEnabled)->dataKey('auto_upgrade_enabled');
        $fields->add('is-public', fn ($value) => confirm(
            label: 'Public?',
            default: (bool) ($value ?? $cache->isPublic),
        ))->currentValue($cache->isPublic)->dataKey('is_public');

        return $fields->get();
    }

    protected function collectDataAndUpdate(array $fields, Cache $cache): Cache
    {
        $selection = multiselect(
            label: 'What do you want to update?',
            options: collect($fields)->mapWithKeys(fn ($field, $key) => [
                $key => $field['label'],
            ])->toArray(),
        );

        if (empty($selection)) {
            $this->outputErrorOrThrow('No fields to update. Select at least one option.');

            exit(self::FAILURE);
        }

        foreach ($selection as $optionName) {
            $field = $fields[$optionName];

            $this->fields()->add($field['key'], fn ($resolver) => $resolver->fromInput(
                fn ($value) => ($field['prompt'])($value ?? $field['current']),
            ));
        }

        return $this->updateCache($cache, $this->fields()->all());
    }

    protected function coerceValue(string $key, mixed $value): mixed
    {
        if ($key === 'auto_upgrade_enabled' || $key === 'is_public') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return $value;
    }
}
