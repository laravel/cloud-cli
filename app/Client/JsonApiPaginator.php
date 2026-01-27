<?php

namespace App\Client;

use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Paginator as BasePaginator;

class JsonApiPaginator extends BasePaginator
{
    protected function isLastPage(Response $response): bool
    {
        $links = $response->json('links', []);

        return ! isset($links['next']) || is_null($links['next']);
    }

    protected function getPageItems(Response $response, Request $request): array
    {
        return $response->json('data', []);
    }

    protected function applyPagination(Request $request): Request
    {
        if ($this->currentResponse instanceof Response) {
            $links = $this->currentResponse->json('links', []);

            if (isset($links['next']) && ! is_null($links['next'])) {
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
