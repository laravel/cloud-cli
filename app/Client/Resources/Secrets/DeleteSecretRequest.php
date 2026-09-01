<?php

namespace App\Client\Resources\Secrets;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeleteSecretRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        protected string $secretId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/secrets/{$this->secretId}";
    }
}
