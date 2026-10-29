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
| `POST /feed` | JSON feed the page loads its tiles from (`public/assets/js/nicave.js`). `/fetchNext.php` is kept as an alias |
| `/maps`, `/maps?year=2019` | Location heatmap for the last 30 days, or a whole year |
| `/today` | Location heatmap for the last day |
| `/lastseen` | JSON of the most recent (rounded) location |

Locations are only ever shown at the API's rounded (`*_vague`, ~1km)
precision: the app calls the API unauthenticated, and the API redacts
precise coordinates for unauthenticated callers.

The frontend (jQuery, Packery, Leaflet, `public/assets/`) is ported as-is from
the pre-Laravel site; modernising it is tracked separately.

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
| `RUN_MIGRATIONS` | Set to `false`: there are no migrations to run |

## Testing

```bash
composer lint:check   # Pint
composer stan         # PHPStan (Larastan)
composer test         # Pest; the Lifestream API is faked with Http::fake()
npm run lint:check    # Biome
```

## Deployment

CI (`.github/workflows/ci.yml`) builds `ghcr.io/aquarion/lifestream-web` and
deploys over SSH to hosts provisioned by
[aquarion/autopelago](https://github.com/aquarion/autopelago) (the
`firth_laravel_app` role, app name `lifestream-web`). The repo needs these
settings:

- Secrets `SSH_HOST`, `SSH_HOST_PRODUCTION`, `SSH_KEY`. Autopelago uploads the deploy key.
- Secret `REBASE_TOKEN`, used by the Dependabot workflows.
- Optionally, the `DEPLOY_NOOP=true` repo variable, until the hosts exist.

Autopelago provides `LIFESTREAM_API_URL`, Redis, and the browser OTel CORS
allow-list for the app.
