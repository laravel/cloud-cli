<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class Organization extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $slug,
    ) {
        //
    }

    public static function fromJsonApi(array $response): self
    {
        $data = $response['data'];
        $attributes = $data['attributes'];

        return self::from([
            'id' => $data['id'],
            'name' => $attributes['name'],
            'slug' => $attributes['slug'],
        ]);
    }
}
