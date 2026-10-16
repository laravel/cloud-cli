<?php

namespace App\Client\Resources\Environments;

use App\Dto\EnvironmentLogPage;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class ListEnvironmentLogsRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param  array<int, string>  $instances
     */
    public function __construct(
        protected string $environmentId,
        protected string $from,
        protected string $to,
        protected ?string $cursor = null,
        protected ?string $type = null,
        protected ?string $queryString = null,
        protected ?bool $caseSensitive = null,
        protected ?bool $wholeWord = null,
        protected array $instances = [],
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/environments/{$this->environmentId}/logs";
    }

    protected function defaultQuery(): array
    {
        return array_filter([
            'from' => $this->from,
            'to' => $this->to,
            'cursor' => $this->cursor,
            'type' => $this->type,
            'query' => $this->queryString,
            'case_sensitive' => $this->caseSensitive,
            'whole_word' => $this->wholeWord,
            'instances' => $this->instances,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    public function createDtoFromResponse(Response $response): EnvironmentLogPage
    {
        return EnvironmentLogPage::createFromResponse($response->json());
    }
}
