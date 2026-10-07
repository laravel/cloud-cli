<?php

namespace App\Client\Resources;

use App\Client\Requests\AttachResendRequestData;
use App\Client\Requests\UpdateResendRequestData;
use App\Client\Resources\Resend\AttachResendRequest;
use App\Client\Resources\Resend\DetachResendRequest;
use App\Client\Resources\Resend\ListResendDomainsRequest;
use App\Client\Resources\Resend\ListResendSendingKeysRequest;
use App\Client\Resources\Resend\UpdateResendRequest;
use App\Dto\Environment;
use App\Dto\ResendSendingKey;
use Saloon\PaginationPlugin\Paginator;

class ResendResource extends Resource
{
    public function domains(?string $name = null, ?string $status = null): Paginator
    {
        return $this->paginate(new ListResendDomainsRequest($name, $status));
    }

    /**
     * @return list<ResendSendingKey>
     */
    public function reusableKeys(string $environmentId): array
    {
        $request = new ListResendSendingKeysRequest($environmentId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function attach(AttachResendRequestData $data): Environment
    {
        $request = new AttachResendRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function update(UpdateResendRequestData $data): Environment
    {
        $request = new UpdateResendRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function detach(string $environmentId): void
    {
        $this->send(new DetachResendRequest($environmentId));
    }
}
