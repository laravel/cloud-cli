<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class EnvironmentLogPage extends Data
{
    /**
     * @param  array<int, EnvironmentLog>  $logs
     */
    public function __construct(
        public readonly array $logs,
        public readonly ?string $cursor = null,
    ) {
        //
    }

    public static function createFromResponse(array $response): self
    {
        $cursor = $response['meta']['cursor'] ?? null;

        return new self(
            logs: collect($response['data'] ?? [])->map(fn ($item) => EnvironmentLog::createFromResponse($item))->values()->all(),
            // The API sends an empty cursor when a page has no logs, so there is nothing older to fetch.
            cursor: $cursor === '' ? null : $cursor,
        );
    }
}
