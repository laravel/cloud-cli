<?php

namespace App\Client\Resources;

use App\Client\Requests\CreateSecretRequestData;
use App\Client\Resources\Secrets\CreateSecretRequest;
use App\Client\Resources\Secrets\GetSecretPublicKeyRequest;
use App\Client\Resources\Secrets\ListSecretsRequest;
use App\Dto\Secret;
use App\Dto\SecretPublicKey;
use Saloon\PaginationPlugin\Paginator;

class SecretsResource extends Resource
{
    public function list(): Paginator
    {
        return $this->paginate(new ListSecretsRequest);
    }

    public function publicKey(): SecretPublicKey
    {
        $request = new GetSecretPublicKeyRequest;
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function create(CreateSecretRequestData $data): Secret
    {
        $request = new CreateSecretRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }
}
