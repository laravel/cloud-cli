<?php

namespace App\Dto;

/**
 * @template TData
 *
 * @property-read TData[] $data
 */
class Paginated extends Data
{
    public function __construct(
        public readonly array $data,
        public readonly array $links,
    ) {
        //
    }

    public function toArray(): array
    {
        return [
            'data' => array_map(fn ($item) => $item->toArray(), $this->data),
            'links' => $this->links,
        ];
    }
}
