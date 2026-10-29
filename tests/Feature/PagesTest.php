<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

function location(array $overrides = []): array
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
    get($path)->assertOk()->assertSee('id="tiles"', false)->assertSee('/assets/js/nicave.js', false);
})->with(['/', '/2019', '/2019/wk12', '/2019/03', '/2019/03/05', '/*/03/05', '/search/hello']);

it('renders the year strip', function () {
    get('/2019/03/05')
        ->assertOk()
        ->assertSee('<a href="/2001/03/05" title="Monday 5th March 2001">2001</a>', false);
});

it('404s on unknown paths', function () {
    get('/nonsense/path/that/is/long')->assertNotFound();
    get('/wp-admin')->assertNotFound();
});

it('renders the 30 day heatmap with rounded coordinates', function () {
    Http::fake(['lifestream.test/v1/locations*' => Http::response([
        location(),
        location(['timestamp' => '2019-03-05T12:05:00']),
        location(['lat_vague' => 51.6, 'title' => 'Work', 'timestamp' => '2019-03-05T13:00:00']),
    ])]);

    $response = get('/maps')->assertOk()->assertSee('Heatmap of locations last 30 days');

    expect($response->getContent())
        ->toContain('{"lat":51.5,"long":-0.12,"title":"2019-03-05 12:00:00","source":"owntracks"}')
        ->toContain('"title":"Work - 2019-03-05 13:00:00"')
        ->toContain('size : 5000')
        ->not->toContain('stamen-maps.a.ssl.fastly.net');
    expect(substr_count($response->getContent(), '"source":"owntracks"'))->toBe(2);
});

it('renders a year heatmap', function () {
    Http::fake(['lifestream.test/v1/locations*' => Http::response([])]);

    get('/maps?year=2018')->assertOk()->assertSee('Heatmap of locations for 2018');

    Http::assertSent(fn (Request $request) => $request['from'] === '2018-01-01T00:00:00' && $request['to'] === '2018-12-31T00:00:00');
});

it('renders the one day heatmap', function () {
    Http::fake(['lifestream.test/v1/locations*' => Http::response([])]);

    get('/today')->assertOk()->assertSee('Heatmap of locations last 1 days')->assertSee('size : 50,', false);
});

it('returns the last seen location, rounded', function () {
    Http::fake(['lifestream.test/v1/locations/latest' => Http::response(location(['title' => 'Home']))]);

    getJson('/lastseen')->assertOk()->assertExactJson([['lat' => 51.5, 'long' => -0.12, 'title' => 'Home']]);
});

it('redirects legacy php URLs, keeping the query string', function () {
    get('/maps.php?year=2019')->assertStatus(301)->assertRedirect('/maps?year=2019');
    get('/today.php')->assertStatus(301)->assertRedirect('/today');
    get('/lastseen.php')->assertStatus(301)->assertRedirect('/lastseen');
});

it('redirects /cv to the CV document', function () {
    get('/cv')->assertRedirectContains('docs.google.com');
});
