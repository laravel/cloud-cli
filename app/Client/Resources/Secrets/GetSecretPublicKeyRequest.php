<?php

namespace App\Client\Resources\Secrets;

use App\Dto\SecretPublicKey;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetSecretPublicKeyRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/secrets/public-key';
    }

    public function createDtoFromResponse(Response $response): SecretPublicKey
    {
        return SecretPublicKey::createFromResponse($response->json());
    }
}
