# Lifestream API — Design Spec

Date: 2026-09-04

## Problem

`lifestream-web` (this repo) has direct MySQL access to a database shared with the sibling
Python project `../Lifestream`. Both projects reading and writing the same tables directly
makes each fragile to the other's changes. This spec defines an HTTP API to sit in front of
that database, starting with everything `lifestream-web` currently needs.

## Scope

Covers every DB touchpoint currently in `lifestream-web`:

- **Reads** (`htdocs/fetchNext.php`, `htdocs/maps.php`, `htdocs/lastseen.php`): the main feed,
  title search, location points, aggregated heatmap points, and "last seen" location.
- **Writes** (`htdocs/api/owntracks.php`, `htdocs/api/plex.php`): OwnTracks location ingest and
  Plex scrobble ingest, which today call `addEntry()`, `add_location()`, and
  `raw_location_data()` in `lib/lifestream.inc.php` directly.

Out of scope: migrating the Python `../Lifestream` importers (`tweets.py`, `foursquare.py`,
etc.) themselves. The two write endpoints are shaped generically enough (mirroring
`addEntry()`/`add_location()` signatures) that those importers could adopt the same API later
without a breaking change, but that migration is not part of this work.

## Data model (from current schema + runtime usage)

- **`lifestream`** — feed entries. Unique on `(type, systemid)`. Fields: `type`, `systemid`,
  `title`, `source`, `date_created`, `date_updated`, `url`, `image`, `fulldata_json`.
- **`lifestream_locations`** — location pings. Fields: `id` (epoch timestamp), `source`,
  `device`, `accuracy`, `lat`, `long`, `alt`, `lat_vague`, `long_vague`, `alt_vague`,
  `timestamp`, `title`, `icon`, `fulldata_json`.
- **`owntracks_unhandled`** — raw dump of OwnTracks payload types the app doesn't otherwise
  handle (e.g. non-`location` events). Write-only from the app's perspective.

## Endpoints

### Reads (no auth — matches today's public pages)

- `GET /v1/entries` — paginated feed. Params: `after` (ISO date-time, filters
  `date_updated >=`, used for polling), `from`/`to` (ISO date-time range on `date_created`),
  `q` — see search below, `offset`, `limit` (default 100, max 200). Server always excludes
  `source in (tumblr, lastfm)` and null `title`, matching current behavior. Response includes
  `items[]` and `total` so the client can compute "X% loaded" itself (current UI behavior).
- `GET /v1/entries/search` — `q` required. Title `LIKE %q%`, ordered ascending by
  `date_created`. Kept as a separate operation (not folded into `/v1/entries?q=`) because it
  changes default ordering and disables date-range filtering, matching the current branch in
  `fetchNext.php`.
- `GET /v1/locations` — raw points in a time range. Params: `from`, `to` (ISO date-time),
  `source`. Backs `maps.php` (1-day and 30-day heatmap pages) and general location browsing.
- `GET /v1/locations/heatmap` — aggregated points: rounds `lat`/`long` to 2 decimal places,
  groups, and counts, mirroring `generate_location_query()` in `fetchNext.php`. Params: `from`,
  `to`, `source` (repeatable, defaults to "all"; the current code's hardcoded
  foursquare-vs-not split becomes `source=foursquare` vs omitting it).
- `GET /v1/locations/latest` — single most recent location point. Backs `lastseen.php`.

### Writes (require `X-API-Key`)

- `POST /v1/entries` — upsert by `(type, systemid)`, mirrors `addEntry()`. Body carries all
  fields `addEntry()` accepts today (`type`, `systemid`, `title`, `source`, `date_created`,
  `url`, `image`, `fulldata_json`, `update` flag). Used today only by `htdocs/api/plex.php`'s
  scrobble handler, but generic enough for future importer reuse.
- `POST /v1/locations` — mirrors `add_location()`: dedup logic (skip if the same source's most
  recent point rounds to the same lat/long) stays server-side. Used today by
  `htdocs/api/owntracks.php`.
- `POST /v1/locations/unhandled` — mirrors `raw_location_data()`: stores `{type, fulldata_json}`
  for OwnTracks payloads the app doesn't otherwise process.

## Auth

Single scheme: API key via `X-API-Key` header, required on all `POST` operations. `GET`
operations are unauthenticated, matching today's public read pages. This closes an existing gap
— the current webhook endpoints (`owntracks.php`, `plex.php`) have no authentication at all.

## Error format

`{ "status": <int>, "message": <string> }`. Simplified from the current `send_json_error()`
shape, which also leaks the internal file path and line number — not appropriate for a public
API contract.

## Deliverable

`docs/api/openapi.yaml` — OpenAPI 3.0 spec covering the endpoints above, with request/response
schemas for `Entry`, `Location`, `HeatmapPoint`, and `Error`.
