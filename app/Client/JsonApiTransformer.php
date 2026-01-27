<?php

namespace App\Client;

class JsonApiTransformer
{
    public static function transform(array $response, ?array $item = null): array
    {
        $data = $item ?? $response['data'];
        $included = $response['included'] ?? [];
        $attributes = $data['attributes'] ?? [];
        $relationships = $data['relationships'] ?? [];

        $transformed = [
            'id' => $data['id'],
        ];

        foreach ($attributes as $key => $value) {
            $transformed[$key] = $value;
        }

        foreach ($relationships as $key => $relationship) {
            $relationshipData = $relationship['data'] ?? null;

            if ($relationshipData === null) {
                continue;
            }

            if (isset($relationshipData['id'])) {
                $transformed[$key.'_id'] = $relationshipData['id'];
            } elseif (is_array($relationshipData)) {
                $transformed[$key.'_ids'] = array_column($relationshipData, 'id');
            }
        }

        return $transformed;
    }

    public static function resolveIncluded(array $included, ?array $relationship, string $type): ?array
    {
        if (! $relationship || ! isset($relationship['data']['id'])) {
            return null;
        }

        $id = $relationship['data']['id'];

        return collect($included)
            ->first(fn ($item) => $item['type'] === $type && $item['id'] === $id);
    }

    public static function resolveIncludedCollection(array $included, ?array $relationship, string $type): array
    {
        if (! $relationship || ! isset($relationship['data']) || ! is_array($relationship['data'])) {
            return [];
        }

        $ids = array_column($relationship['data'], 'id');

        return collect($included)
            ->filter(fn ($item) => $item['type'] === $type && in_array($item['id'], $ids))
            ->values()
            ->toArray();
    }
}
