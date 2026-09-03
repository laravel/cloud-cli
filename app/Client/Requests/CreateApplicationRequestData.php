<?php

namespace App\Client\Requests;

class CreateApplicationRequestData extends RequestData
{
    public readonly ?string $rootDirectory;

    public function __construct(
        public readonly string $repository,
        public readonly string $name,
        public readonly string $region,
        ?string $rootDirectory = null,
    ) {
        // The API rejects a trailing slash, which is what shell completion gives you.
        $rootDirectory = rtrim((string) $rootDirectory, '/');

        $this->rootDirectory = $rootDirectory === '' ? null : $rootDirectory;
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
