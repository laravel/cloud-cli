<?php

namespace App\Client\Resources\Environments;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class AddEnvironmentVariablesRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param  'append'|'set'  $method
     */
    public function __construct(
        protected string $environmentId,
        protected array $variables,
        protected string $method = 'append',
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/environments/{$this->environmentId}/variables";
    }

    protected function defaultBody(): array
    {
        return [
            'method' => $this->method,
            'variables' => collect($this->variables)->map(fn ($value, $key) => [
                'key' => $key,
                'value' => $value,
            ])->values()->toArray(),
        ];
    }
}
