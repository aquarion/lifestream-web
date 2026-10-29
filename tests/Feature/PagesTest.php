<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

function apiLocation(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'source' => 'owntracks',
        'device' => 'np',
        'lat' => null,
        'long' => null,
        'lat_vague' => 51.5,
        'long_vague' => -0.12,
        'accuracy' => 0,
        'title' => '',
        'icon' => null,
        'timestamp' => '2019-03-05T12:00:00',
        'fulldata_json' => null,
    ], $overrides);
}

it('renders the homepage shell', function (string $path) {
    get($path)->assertOk()->assertSee('id="tiles"', false);
})->with(['/', '/2019', '/2019/wk12', '/2019/03', '/2019/03/05', '/*/03/05', '/search/hello']);

it('renders the year strip', function () {
    get('/2019/03/05')
        ->assertOk()
        ->assertSee('<a href="/2001/03/05" title="Monday 5th March 2001">2001</a>', false);
});

it('404s on unknown paths and impossible dates', function (string $path) {
    get($path)->assertNotFound();
})->with(['/nonsense/path/that/is/long', '/wp-admin', '/2019/13/45', '/2019/wk99', '/search']);

it('renders the 30 day heatmap with rounded coordinates', function () {
    Http::fake(['lifestream.test/v1/locations*' => Http::response([
        apiLocation(),
        apiLocation(['timestamp' => '2019-03-05T12:05:00']),
        apiLocation(['lat_vague' => 51.6, 'title' => 'Work', 'timestamp' => '2019-03-05T13:00:00']),
    ])]);

    get('/maps')
        ->assertOk()
        ->assertViewHas('title', 'Heatmap of locations last 30 days')
        ->assertViewHas('heatmapRadius', 20)
        ->assertViewHas('locations', [
            ['lat' => 51.5, 'long' => -0.12, 'title' => '2019-03-05 12:00:00', 'source' => 'owntracks'],
            ['lat' => 51.6, 'long' => -0.12, 'title' => 'Work - 2019-03-05 13:00:00', 'source' => 'owntracks'],
        ])
        ->assertDontSee('stamen-maps.a.ssl.fastly.net');
});

it('renders a calendar year heatmap', function () {
    Http::fake(['lifestream.test/v1/locations*' => Http::response([])]);

    get('/maps?year=2018')->assertOk()->assertViewHas('title', 'Heatmap of locations for 2018');

    Http::assertSent(fn (Request $request) => $request['from'] === '2018-01-01T00:00:00' && $request['to'] === '2019-01-01T00:00:00');
});

it('ignores out-of-range years', function () {
    Http::fake(['lifestream.test/v1/locations*' => Http::response([])]);

    get('/maps?year=1066')->assertOk()->assertViewHas('title', 'Heatmap of locations last 30 days');
});

it('renders the one day heatmap up to now', function () {
    Http::fake(['lifestream.test/v1/locations*' => Http::response([])]);

    get('/today')
        ->assertOk()
        ->assertViewHas('title', 'Heatmap of locations last 1 days')
        ->assertViewHas('heatmapRadius', 12);

    Http::assertSent(fn (Request $request) => $request['to'] === date('Y-m-d\TH:i:s')
        || $request['to'] === date('Y-m-d\TH:i:s', time() - 1));
});

it('returns the last seen location, rounded', function () {
    Http::fake(['lifestream.test/v1/locations/latest' => Http::response(apiLocation(['title' => 'Home']))]);

    getJson('/lastseen')->assertOk()->assertExactJson([['lat' => 51.5, 'long' => -0.12, 'title' => 'Home']]);
});

it('returns an empty list when no location has been recorded', function () {
    Http::fake(['lifestream.test/v1/locations/latest' => Http::response(['detail' => 'No location points recorded yet.'], 404)]);

    getJson('/lastseen')->assertOk()->assertExactJson([]);
});

it('serves 503s when the API is down', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    get('/maps')->assertStatus(503);
    get('/today')->assertStatus(503);
    getJson('/lastseen')->assertStatus(503)->assertJsonPath('status', 503);
});

it('redirects legacy php URLs, keeping the query string', function () {
    get('/maps.php?year=2019')->assertStatus(301)->assertRedirect('/maps?year=2019');
    get('/today.php')->assertStatus(301)->assertRedirect('/today');
    get('/lastseen.php')->assertStatus(301)->assertRedirect('/lastseen');
});

it('serves the files browsers fetch from fixed paths', function () {
    get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8');
    get('/favicon.ico')->assertStatus(301);
    getJson('/site.webmanifest')->assertOk()->assertJsonPath('name', 'NicAve')->assertJsonCount(2, 'icons');
});

it('redirects /cv to the CV document', function () {
    get('/cv')->assertRedirectContains('docs.google.com');
});

it('only keeps location icons that are URLs', function () {
    Http::fake(['lifestream.test/v1/locations*' => Http::response([
        apiLocation(['icon' => '74']),
        apiLocation(['lat_vague' => 51.6, 'icon' => 'https://example.com/pin_64.png']),
    ])]);

    get('/maps')->assertOk()->assertViewHas('locations', fn (array $locations) => ! isset($locations[0]['icon'])
        && $locations[1]['icon'] === 'https://example.com/pin_64.png');
});
