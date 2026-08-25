<?php

namespace App\Client\Requests;

class CreateApplicationRequestData extends RequestData
{
    public function __construct(
        public readonly string $repository,
        public readonly string $name,
        public readonly string $region,
        public readonly ?string $rootDirectory = null,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return $this->filter([
            'repository' => $this->repository,
            'name' => $this->name,
            'region' => $this->region,
            'root_directory' => $this->rootDirectory,
        ]);
    }
}
