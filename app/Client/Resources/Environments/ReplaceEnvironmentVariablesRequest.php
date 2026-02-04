<?php

namespace App\Client\Resources\Environments;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class ReplaceEnvironmentVariablesRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    public function __construct(
        protected string $environmentId,
        protected ?string $content = null,
        protected array $variables = [],
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/environments/{$this->environmentId}/variables";
    }

    protected function defaultBody(): array
    {
        $body = [];

        if ($this->content !== null) {
            $body['content'] = $this->content;
        }

        if ($this->variables !== []) {
            $body['variables'] = collect($this->variables)->map(fn ($value, $key) => [
                'key' => $key,
                'value' => $value,
            ])->values()->toArray();
        }

        return $body;
    }
}
