<?php

namespace App\Client\Resources;

use App\Client\Resources\Usage\GetUsageRequest;
use App\Dto\Usage;

class UsageResource extends Resource
{
    public function get(int $period = 0, ?string $environment = null): Usage
    {
        $request = new GetUsageRequest($period, $environment);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }
}
