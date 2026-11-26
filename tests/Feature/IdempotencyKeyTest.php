<?php

use App\Client\Connector;
use App\Client\Requests\StartEnvironmentRequestData;
use App\Client\Requests\UpdateEnvironmentRequestData;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\StartEnvironmentRequest;
use App\Client\Resources\Environments\UpdateEnvironmentRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;

function sentIdempotencyKey(Request $request): ?string
{
    $connector = new Connector('test-api-token');
    $connector->withMockClient(new MockClient([MockResponse::make(['data' => []])]));

    return $connector->send($request)->getPendingRequest()->headers()->get('Idempotency-Key');
}

it('sends a new idempotency key with each POST and PATCH request', function () {
    $start = sentIdempotencyKey(new StartEnvironmentRequest(new StartEnvironmentRequestData('env-1')));
    $otherStart = sentIdempotencyKey(new StartEnvironmentRequest(new StartEnvironmentRequestData('env-1')));
    $update = sentIdempotencyKey(new UpdateEnvironmentRequest(new UpdateEnvironmentRequestData('env-1', name: 'prod')));

    expect($start)->toBeUuid()
        ->and($update)->toBeUuid()
        ->and($otherStart)->not->toBe($start);
});

it('reuses the idempotency key when the same request is sent again', function () {
    $request = new StartEnvironmentRequest(new StartEnvironmentRequestData('env-1'));

    expect(sentIdempotencyKey($request))->toBe(sentIdempotencyKey($request));
});

it('sends no idempotency key with GET requests', function () {
    expect(sentIdempotencyKey(new GetEnvironmentRequest('env-1')))->toBeNull();
});
