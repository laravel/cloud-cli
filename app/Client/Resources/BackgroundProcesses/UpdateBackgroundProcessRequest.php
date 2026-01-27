<?php

namespace App\Client\Resources\BackgroundProcesses;

use App\Dto\BackgroundProcess;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class UpdateBackgroundProcessRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PATCH;

    public function __construct(
        protected string $backgroundProcessId,
        protected array $data,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/background-processes/{$this->backgroundProcessId}";
    }

    protected function defaultBody(): array
    {
        return $this->data;
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return BackgroundProcess::createFromResponse($response->json());
    }
}
