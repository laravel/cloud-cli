<?php

namespace App\Client\Resources\Resend;

use App\Dto\ResendSendingKey;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class ListResendSendingKeysRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $environmentId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/environments/{$this->environmentId}/resend/keys";
    }

    /**
     * @return list<ResendSendingKey>
     */
    public function createDtoFromResponse(Response $response): array
    {
        return array_map(
            fn (array $item) => ResendSendingKey::createFromResponse($item),
            $response->json('data') ?? [],
        );
    }
}
