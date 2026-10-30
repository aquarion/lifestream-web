<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

function apiEntry(array $overrides = []): array
{
    return array_merge([
        'type' => 'twitter',
        'systemid' => '123',
        'title' => 'Hello',
        'source' => 'Twitter',
        'url' => null,
        'image' => null,
        'date_created' => '2019-03-05T12:00:00',
        'date_updated' => '2019-03-05T12:00:00',
        'fulldata_json' => ['text' => 'Hello'],
    ], $overrides);
}

function apiPage(array $items, int $total, int $offset = 0): array
{
    return ['items' => $items, 'total' => $total, 'offset' => $offset, 'limit' => 100];
}

it('returns a day of entries in the legacy feed shape', function () {
    Http::fake([
        'lifestream.test/v1/entries*' => Http::response(apiPage([apiEntry()], 1)),
        'lifestream.test/v1/locations/heatmap*' => Http::response([
            ['lat' => 51.5, 'long' => -0.12, 'count' => 4, 'title' => 'Home', 'icon' => null],
            ['lat' => 0, 'long' => 0, 'count' => 1, 'title' => null, 'icon' => null],
        ]),
    ]);

    $response = postJson('/feed', ['path' => '/2019/03/05'])->assertOk();

    $response->assertJson([
        'status' => 200,
        'next' => 30,
        'offset' => 0,
        'max' => 1,
        'direction' => 'append',
        'message' => 'Day from 2019-03-05 03:00 to 2019-03-06 03:00',
        'nav' => ['back' => '/2019/03/04', 'forward' => '/2019/03/06', 'up' => '/2019/wk10'],
        'locations' => [['lat' => 51.5, 'long' => -0.12, 'title' => 'Home', 'value' => 4]],
    ]);
    expect($response->json('locations'))->toHaveCount(1)
        ->and($response->json('items.0.fulldata_json'))->toBe(['text' => 'Hello'])
        ->and($response->json('items.0.url'))->toBe('')
        ->and($response->json('items.0.image'))->toBe('')
        ->and($response->json())->not->toHaveKey('log');

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://lifestream.test/v1/entries')
        && $request['from'] === '2019-03-05T03:00:00'
        && $request['to'] === '2019-03-06T03:00:00'
        && ! isset($request['order']));
});

it('pages through results and reports progress', function () {
    Http::fake(['lifestream.test/v1/entries*' => Http::response(apiPage([apiEntry()], 250))]);

    $response = postJson('/feed', ['path' => '/2019/03', 'offset' => 100])->assertOk();

    expect($response->json('offset'))->toBe(200)
        ->and($response->json('next'))->toBe(2)
        ->and($response->json('message'))->toBe('Month from 2019-03-01 to 2019-03-31 (80% Loaded)')
        ->and($response->json())->not->toHaveKey('locations');

    Http::assertSent(fn (Request $request) => (int) $request['offset'] === 100);
});

it('shows the most recent 200 items newest first', function () {
    Http::fake([
        'lifestream.test/v1/entries*' => Http::response(apiPage([apiEntry()], 5000)),
        'lifestream.test/v1/locations/heatmap*' => Http::response([]),
    ]);

    $first = postJson('/feed', ['path' => '/'])->assertOk();
    $second = postJson('/feed', ['path' => '/', 'offset' => 100])->assertOk();

    expect($first->json('title'))->toBe(' Last 200 Items')
        ->and($first->json('offset'))->toBe(100)
        ->and($first->json('message'))->toContain('(50% Loaded)')
        ->and($second->json('offset'))->toBe(0);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/entries') && $request['order'] === 'desc');
});

it('polls for updates with after', function () {
    Http::fake([
        'lifestream.test/v1/entries*' => Http::response(apiPage([], 0)),
        'lifestream.test/v1/locations/heatmap*' => Http::response([]),
    ]);

    postJson('/feed', ['path' => '/', 'after' => '2019-03-05 12:00:00'])
        ->assertOk()
        ->assertJson(['direction' => 'prepend']);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/entries') && $request['after'] === '2019-03-05 12:00:00');
});

it('searches titles', function () {
    Http::fake(['lifestream.test/v1/entries/search*' => Http::response(apiPage([apiEntry()], 1))]);

    postJson('/feed', ['path' => '/search/hello%20world'])
        ->assertOk()
        ->assertJson(['title' => 'Search for "hello world"', 'today' => false])
        ->assertJsonMissingPath('locations');

    Http::assertSent(fn (Request $request) => $request['q'] === 'hello world');
});

it('passes the viewer\'s excluded sources to the API', function () {
    Http::fake(['lifestream.test/v1/entries*' => Http::response(apiPage([], 0))]);

    postJson('/feed', ['path' => '/*/03/05', 'exclude' => ['tumblr', 'lastfm', '', ['nested']]])->assertOk();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'exclude_source=tumblr&exclude_source=lastfm')
        && substr_count($request->url(), 'exclude_source=') === 2);
});

it('passes excluded sources to search', function () {
    Http::fake(['lifestream.test/v1/entries/search*' => Http::response(apiPage([], 0))]);

    postJson('/feed', ['path' => '/search/x', 'exclude' => ['tumblr']])->assertOk();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'exclude_source=tumblr'));
});

it('excludes no sources unless asked', function () {
    Http::fake(['lifestream.test/v1/entries*' => Http::response(apiPage([], 0))]);

    postJson('/feed', ['path' => '/*/03/05', 'exclude' => 'tumblr'])->assertOk();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/entries') && ! str_contains($request->url(), 'exclude_source'));
});

it('filters any-year days by month and day', function () {
    Http::fake(['lifestream.test/v1/entries*' => Http::response(apiPage([], 0))]);

    postJson('/feed', ['path' => '/*/03/05'])->assertOk();

    Http::assertSent(fn (Request $request) => (int) $request['month'] === 3 && (int) $request['day'] === 5 && ! isset($request['from']));
});

it('proxies off-site images', function () {
    Http::fake([
        'lifestream.test/v1/entries*' => Http::response(apiPage([
            apiEntry(['image' => 'https://example.com/a.png']),
            apiEntry(['image' => 'http://art.istic.net/icon.png']),
            apiEntry(['image' => '0']),
        ], 3)),
        'lifestream.test/v1/locations/heatmap*' => Http::response([]),
    ]);

    $response = postJson('/feed', ['path' => '/2019'])->assertOk();

    expect($response->json('items.0.image'))->toBe('https://art.istic.net/lifestreamprox/?url=https%3A%2F%2Fexample.com%2Fa.png')
        ->and($response->json('items.1.image'))->toBe('https://art.istic.net/icon.png')
        ->and($response->json('items.2.image'))->toBe('');
});

it('shows xkcd for the future without calling the API', function () {
    postJson('/feed', ['path' => '/2099'])
        ->assertOk()
        ->assertJsonPath('items.0.source', 'xkcd');

    Http::assertNothingSent();
});

it('returns a JSON 503 when the API errors', function () {
    Http::fake(['lifestream.test/*' => Http::response(['detail' => 'boom'], 500)]);

    postJson('/feed', ['path' => '/2019'])
        ->assertStatus(503)
        ->assertExactJson(['status' => 503, 'message' => 'The lifestream is unavailable right now.']);
});

it('returns a JSON 503 when the API is unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    postJson('/feed', ['path' => '/2019'])->assertStatus(503)->assertJsonPath('status', 503);
});

it('still returns entries when only the heatmap fails', function () {
    Http::fake([
        'lifestream.test/v1/entries*' => Http::response(apiPage([apiEntry()], 1)),
        'lifestream.test/v1/locations/heatmap*' => Http::response(['detail' => 'boom'], 500),
    ]);

    postJson('/feed', ['path' => '/2019/03/05'])
        ->assertOk()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('locations', []);
});

it('escapes search terms in the message', function () {
    Http::fake(['lifestream.test/v1/entries/search*' => Http::response(apiPage([], 0))]);

    postJson('/feed', ['path' => '/search/%3Cimg%20src%3Dx%20onerror%3Dalert(1)%3E'])
        ->assertOk()
        ->assertJsonPath('message', 'Search for "&lt;img src=x onerror=alert(1)&gt;"');

    Http::assertSent(fn (Request $request) => $request['q'] === '<img src=x onerror=alert(1)>');
});

it('falls back to the homepage feed for unknown paths', function () {
    Http::fake([
        'lifestream.test/v1/entries*' => Http::response(apiPage([], 0)),
        'lifestream.test/v1/locations/heatmap*' => Http::response([]),
    ]);

    postJson('/feed', ['path' => '/nonsense'])->assertOk()->assertJsonPath('title', ' Last 200 Items');
});

it('still serves the legacy fetchNext.php URL', function () {
    Http::fake(['lifestream.test/v1/entries/search*' => Http::response(apiPage([], 0))]);

    post('/fetchNext.php', ['path' => '/search/x'])->assertOk()->assertJsonPath('status', 200);
});
