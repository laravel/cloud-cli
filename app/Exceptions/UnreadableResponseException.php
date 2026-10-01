<?php

namespace App\Exceptions;

use Exception;
use Saloon\Http\Response;

class UnreadableResponseException extends Exception
{
    public static function for(Response $response): self
    {
        $request = $response->getPendingRequest();

        if ($response->redirect()) {
            return new self(sprintf(
                'Laravel Cloud redirected %s %s to %s instead of answering (HTTP %d). Check that your API token is valid and that your organization can access this repository in the Laravel Cloud dashboard.',
                $request->getMethod()->value,
                $request->getUrl(),
                $response->header('Location') ?? 'an unknown location',
                $response->status(),
            ));
        }

        return new self(sprintf(
            'Laravel Cloud sent back a response we could not read: HTTP %d from %s %s. The body started with: %s',
            $response->status(),
            $request->getMethod()->value,
            $request->getUrl(),
            str($response->body())->squish()->limit(80)->toString(),
        ));
    }
}
