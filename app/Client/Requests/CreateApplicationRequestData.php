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
        $data = [
            'repository' => $this->repository,
            'name' => $this->name,
            'region' => $this->region,
        ];

        if ($this->rootDirectory !== null) {
            $data['root_directory'] = $this->rootDirectory;
        }

        return $data;
    }
}
