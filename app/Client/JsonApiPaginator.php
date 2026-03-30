<?php

namespace App\Client;

use Closure;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Paginator as BasePaginator;

class JsonApiPaginator extends BasePaginator
{
    protected ?Closure $itemTransformer = null;

    public function transform(Closure $transformer): self
    {
        $this->itemTransformer = $transformer;

        return $this;
    }

    protected function isLastPage(Response $response): bool
    {
        $links = $response->json('links', []);

        return ! isset($links['next']);
    }

    protected function getPageItems(Response $response, Request $request): array
    {
        $items = $response->json('data', []);

        if ($this->itemTransformer) {
            $responseData = $response->json();

            return array_filter(array_map(function ($item) use ($responseData) {
                if (! is_array($item) || empty($item)) {
                    return null;
                }

                return ($this->itemTransformer)($responseData, $item);
            }, $items));
        }

        return $items;
    }

    protected function applyPagination(Request $request): Request
    {
        if ($this->currentResponse instanceof Response) {
            $links = $this->currentResponse->json('links', []);

            if (isset($links['next'])) {
                $nextUrl = parse_url($links['next']);
                parse_str($nextUrl['query'] ?? '', $queryParams);

                foreach ($queryParams as $key => $value) {
                    $request->query()->add($key, $value);
                }
            }
        }

        return $request;
    }
}
