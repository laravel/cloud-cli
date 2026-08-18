<?php

namespace App\Exceptions;

use Exception;
use Saloon\Http\Response;

class UnreadableResponseException extends Exception
{
    public static function for(Response $response): self
    {
        $request = $response->getPendingRequest();

        return new self(sprintf(
            'Laravel Cloud sent back a response we could not read: HTTP %d from %s %s. The body started with: %s',
            $response->status(),
            $request->getMethod()->value,
            $request->getUrl(),
            str($response->body())->squish()->limit(80)->toString(),
        ));
    }
}
