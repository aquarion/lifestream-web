<?php

use App\Services\LifestreamApi;
use App\Services\LifestreamApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

function api(): LifestreamApi
{
    return new LifestreamApi('https://lifestream.test/v1/', 5);
}

it('sends entry filters, dropping unset ones', function () {
    Http::fake(['*' => Http::response(['items' => [], 'total' => 0, 'offset' => 0, 'limit' => 100])]);

    api()->entries(
        from: new DateTimeImmutable('2019-03-05 03:00:00'),
        to: new DateTimeImmutable('2019-03-06 03:00:00'),
        offset: 100,
    );

    Http::assertSent(fn (Request $request) => $request->url() === 'https://lifestream.test/v1/entries?from=2019-03-05T03%3A00%3A00&to=2019-03-06T03%3A00%3A00&offset=100&limit=100');
});

it('only sends order when it is not the default', function () {
    Http::fake(['*' => Http::response(['items' => [], 'total' => 0, 'offset' => 0, 'limit' => 100])]);

    api()->entries(order: 'desc', month: 3, day: 5);

    Http::assertSent(fn (Request $request) => $request['order'] === 'desc'
        && (int) $request['month'] === 3
        && (int) $request['day'] === 5
        && ! isset($request['from']));
});

it('throws with the status when the API errors', function () {
    Http::fake(['*' => Http::response(['detail' => 'Rate limit exceeded'], 429)]);

    expect(fn () => api()->search('x'))->toThrow(
        LifestreamApiException::class,
        'Lifestream API /entries/search returned 429: Rate limit exceeded',
    );
});

it('throws when the API is unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    expect(fn () => api()->heatmap(new DateTimeImmutable))->toThrow(LifestreamApiException::class, 'unreachable');
});

it('treats the API\'s own 404 as no latest location', function () {
    Http::fake(['*' => Http::response(['detail' => 'No location points recorded yet.'], 404)]);

    expect(api()->latestLocation())->toBeNull();
});

it('treats any other 404 as an error', function (mixed $body) {
    Http::fake(['*' => Http::response($body, 404)]);

    expect(fn () => api()->latestLocation())->toThrow(LifestreamApiException::class);
})->with([
    'non-JSON page' => '<html>Not Found</html>',
    'FastAPI unknown route (wrong base URL)' => [['detail' => 'Not Found']],
]);
