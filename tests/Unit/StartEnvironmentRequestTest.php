<?php

use App\Client\Connector;
use App\Client\Resources\Environments\StartEnvironmentRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

it('sends redeploy only when given', function (?bool $redeploy, array $expected) {
    $body = null;

    $connector = new Connector('test-api-token');
    $connector->withMockClient(new MockClient([
        StartEnvironmentRequest::class => function (PendingRequest $request) use (&$body) {
            $body = $request->body()?->all() ?? [];

            return MockResponse::make([], 204);
        },
    ]));

    $connector->environments()->start('env-1', $redeploy);

    expect($body)->toBe($expected);
})->with([
    'omitted' => [null, []],
    'true' => [true, ['redeploy' => true]],
    'false' => [false, ['redeploy' => false]],
]);
