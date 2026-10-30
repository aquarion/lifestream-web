# lifestream-web

The lifestream at [nicholasavenell.com](https://nicholasavenell.com): a page of
everything various services have seen me do, with location heatmaps.

This is a Laravel 13 app built from
[aquarion/laravel-starter-kit](https://github.com/aquarion/laravel-starter-kit).
It has no database of its own. All data comes from the Lifestream data API
in [aquarion/lifestream](https://github.com/aquarion/lifestream) (`/v1`, see
`docs/api/openapi.yaml` there), which also receives OwnTracks location
updates directly at `/v1/owntracks`.

## Pages

| URL | What |
|---|---|
| `/` | The last 200 items, newest first |
| `/2024`, `/2024/03`, `/2024/wk12`, `/2024/03/05` | A year, month, week or day (days run 03:00–03:00) |
| `/*/03/05` | The same day in every year |
| `/search/term` | Title search |
| `POST /feed` | JSON feed the page loads its tiles from (`resources/js/lifestream/feed.js`). `/fetchNext.php` is kept as an alias |
| `/maps`, `/maps?year=2019` | Location heatmap for the last 30 days, or a calendar year |
| `/today` | Location heatmap for the last 24 hours |
| `/lastseen` | JSON of the most recent (rounded) location |

Locations are only ever shown at the API's rounded precision (`*_vague`:
2 decimal places, roughly 1 km): the app calls the API unauthenticated, and the API redacts
precise coordinates for unauthenticated callers.

The frontend is bundled by Vite from `resources/`:

- `resources/js/lifestream/home.js` is the homepage tiles. `feed.js` loads them from `/feed`, `formatting.js` styles each service's tiles, and they're laid out with Packery. `filter.js` is the filter menu in the top left, with a checkbox per entry source: unchecked sources are sent to `/feed` as `exclude[]` and on to the API as `exclude_source` (tumblr and lastfm start unchecked), and remembered in the viewer's localStorage.
- `resources/js/lifestream/heatmap.js` is the `/maps` and `/today` pages, using Leaflet and leaflet.heat (`map.js`).
- `resources/css/lifestream.css` is the site's stylesheet, and `resources/images/` holds icons, referenced with `Vite::asset()`.

firth's nginx serves only `public/build/` from disk, so every static file has
to go through Vite. `/favicon.ico`, `/robots.txt` and `/site.webmanifest` are
served by routes instead.

## Getting started

```bash
composer setup
```

Then set `LIFESTREAM_API_URL` in `.env` to a running Lifestream API, including
the version prefix (e.g. `https://lifestream.example.com/v1`), and run
`composer dev`.

`composer.lock` targets PHP 8.4, which CI and the production image use.
`ext-opentelemetry` is a platform requirement; see the starter kit's README
for installing it, or run Composer with
`--ignore-platform-req=ext-opentelemetry` if you don't need backend
OpenTelemetry locally.

### Configuration

| Variable | |
|---|---|
| `LIFESTREAM_API_URL` | Base URL of the Lifestream API, including `/v1` |
| `LIFESTREAM_API_TIMEOUT` | Seconds, default 5 |
| `APP_TIMEZONE` | Default `Europe/London`. Date ranges are sent to the API as wall-clock times in this zone |
| `SESSION_DRIVER`, `CACHE_STORE`, `REDIS_HOST` | Sessions and cache use Redis |

## Testing

```bash
composer lint:check   # Pint
composer stan         # PHPStan (Larastan)
composer test         # Pest; the Lifestream API is faked with Http::fake()
npm run lint:check    # Biome
```

## Deployment

CI (`.github/workflows/ci.yml`) builds `ghcr.io/aquarion/lifestream-web` and
deploys over SSH to firth, as provisioned by
[aquarion/autopelago](https://github.com/aquarion/autopelago) (the
`firth_laravel_app` role, app name `lifestream-web`). Staging deploys to
`firth.istic.systems` and production to `firth.water.gkhs.net`, matching
other firth apps such as bloom.

The secrets come from autopelago's bootstrap run:

- `FIRTH_SSH_KEY`, the app user's deploy key.
- `REBASE_TOKEN`, used by the Dependabot workflows.

Until they exist, the deploy jobs fail. Set the `DEPLOY_NOOP=true` repo
variable to skip them in the meantime.

Autopelago provides `LIFESTREAM_API_URL`, Redis, and the browser OTel CORS
allow-list for the app.
