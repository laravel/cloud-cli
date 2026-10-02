<?php

namespace App\Dto;

class EnvironmentLogPage
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

    public function hasMore(): bool
    {
        return $this->logs !== [] && $this->cursor !== null && $this->cursor !== '';
    }
}
