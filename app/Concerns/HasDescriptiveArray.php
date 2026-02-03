<?php

namespace App\Concerns;

trait HasDescriptiveArray
{
    public function descriptiveArray(): array
    {
        return collect($this->toArray())
            ->mapWithKeys(fn ($value, $key) => $this->processDescriptionItem($key, $value))
            ->toArray();
    }

    protected function descriptiveKey(string $key): string
    {
        return match ($key) {
            'id' => 'ID',
            default => str($key)->replaceMatches('/[A-Z]/', ' $0')->title()->replace('Id', 'ID')->toString(),
        };
    }

    protected function processDescriptionItem(string $key, mixed $value): array
    {
        if ($value = $this->overrideDescriptionItem($key, $value)) {
            return $value;
        }

        return [
            $this->descriptiveKey($key) => $value,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function overrideDescriptionItem(string $key, mixed $value): ?array
    {
        return null;
    }
}
