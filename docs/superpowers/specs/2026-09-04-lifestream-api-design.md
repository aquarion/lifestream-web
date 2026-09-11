# Lifestream API — Design Spec

Date: 2026-09-04

## Problem

`lifestream-web` (this repo) has direct MySQL access to a database shared with the sibling
Python project `../Lifestream`. Both projects reading and writing the same tables directly
makes each fragile to the other's changes. This spec defines an HTTP API to sit in front of
that database, starting with everything `lifestream-web` currently needs.

## Scope

Covers every DB touchpoint currently in `lifestream-web`:

- **Reads** (`htdocs/fetchNext.php`, `htdocs/maps.php`, `htdocs/lastseen.php`, `htdocs/today.php`):
  the main feed, title search, location points, aggregated heatmap points, and "last seen"
  location. `today.php` builds its one-day heatmap from the same `lifestream_locations` queries
  as `maps.php` and is covered by the same `GET /v1/locations`/`GET /v1/locations/heatmap`
  endpoints.
- **Writes** (`htdocs/api/owntracks.php`, `htdocs/api/plex.php`): OwnTracks location ingest and
  Plex scrobble ingest, which today call `addEntry()`, `add_location()`, and
  `raw_location_data()` in `lib/lifestream.inc.php` directly.

Out of scope: migrating the Python `../Lifestream` importers (`tweets.py`, `foursquare.py`,
etc.) themselves, and `contrib/owntracks_import.php` (a standalone maintenance script that
replays `owntracks_unhandled` rows through `add_location()`). All of these call the same
`addEntry()`/`add_location()`/`raw_location_data()` helpers the write endpoints mirror, so they
could adopt the API later without a breaking change, but that migration is not part of this
work.

## Data model (from current schema + runtime usage)

- **`lifestream`** — feed entries. Unique on `(type, systemid)`. Fields: `type`, `systemid`,
  `title`, `source`, `date_created`, `date_updated`, `url`, `image`, `fulldata_json`.
- **`lifestream_locations`** — location pings. Fields: `id` (epoch timestamp), `source`,
  `device`, `accuracy`, `lat`, `long`, `alt`, `lat_vague`, `long_vague`, `alt_vague`,
  `timestamp`, `title`, `icon`, `fulldata_json`.
- **`owntracks_unhandled`** — raw dump of OwnTracks payload types the app doesn't otherwise
  handle (e.g. non-`location` events). Write-only from the app's perspective.

## Endpoints

### Reads

- `GET /v1/entries` — paginated feed. Params: `after` (ISO date-time, filters
  `date_updated >=`, used for polling), `from`/`to` (ISO date-time range on `date_created`),
  `offset`, `limit` (default 100, max 200). Server always excludes `source in (tumblr, lastfm)`
  and null `title`, matching current behavior. Response includes `items[]` and `total` so the
  client can compute "X% loaded" itself (current UI behavior). Title search is a separate
  operation — see below. Unauthenticated, matching today's public feed page.
- `GET /v1/entries/search` — `q` required. Title `LIKE %q%`, ordered ascending by
  `date_created`. Kept as a separate operation (not folded into `/v1/entries?q=`) because it
  changes default ordering and disables date-range filtering, matching the current branch in
  `fetchNext.php`. Unauthenticated.
- `GET /v1/locations` — points in a time range. Params: `from`, `to` (ISO date-time), `source`.
  Backs `maps.php` (1-day and 30-day heatmap pages) and general location browsing. Unauthenticated
  callers get redacted points (rounded `lat_vague`/`long_vague` only, `lat`/`long`/
  `fulldata_json` null) — see Auth below; today's public map pages only ever show the rounded
  values, so this is a mirror, not a new restriction.
- `GET /v1/locations/heatmap` — aggregated points: rounds `lat`/`long` to 2 decimal places,
  groups, and counts, mirroring `generate_location_query()` in `fetchNext.php`. Params: `from`,
  `to`, `source` (single value; omit for all sources merged into one set of rounded-coordinate
  groups). This is an intentional simplification, not a mirror: `fetchNext.php` currently runs a
  separate foursquare-only query alongside the main one, but only ever returns the main query's
  rows to the client (`$foursquare_rows` is computed and merged into `$location_rows`, which is
  never read) — so today's foursquare split has no visible effect. `source=foursquare` is kept
  as an explicit filter for when that data is wanted, without preserving the dead branching.
  Unauthenticated — points are already rounded, so there's nothing to redact.
- `GET /v1/locations/latest` — single most recent location point. Backs `lastseen.php`. Same
  redaction as `GET /v1/locations` for unauthenticated callers.

### Writes (require `X-API-Key`)

- `POST /v1/entries` — upsert by `(type, systemid)`, mirrors `addEntry()`. Body carries all
  fields `addEntry()` accepts today (`type`, `systemid`, `title`, `source`, `date_created`,
  `url`, `image`, `fulldata_json`, `update` flag). Used today only by `htdocs/api/plex.php`'s
  scrobble handler, but generic enough for future importer reuse.
- `POST /v1/locations` — mirrors `add_location()`: dedup logic (skip if the same source's most
  recent point rounds to the same lat/long) stays server-side. Used today by
  `htdocs/api/owntracks.php`.
- `POST /v1/locations/unhandled` — mirrors `raw_location_data()`: archives `{type, fulldata_json}`
  for every OwnTracks payload received, including `location` events (`owntracks.php` calls
  `raw_location_data()` unconditionally, after the `location`-specific `add_location()` call) —
  not just types the app doesn't otherwise turn into a `Location`.

## Auth

API key via `X-API-Key` header.

- **Writes** (`POST`) require it. This closes an existing gap — the current webhook endpoints
  (`owntracks.php`, `plex.php`) have no authentication at all.
- **Reads** (`GET`) don't require it, but `GET /v1/locations` and `GET /v1/locations/latest`
  accept it optionally: without a key, precise `lat`/`long` and `fulldata_json` are redacted to
  null and only the rounded `lat_vague`/`long_vague` are returned; with a valid key, the precise
  fields are included. This matters because those two endpoints, unlike the rest of the reads,
  would otherwise expose exact location history and raw payloads that today's public pages
  (`maps.php`, `lastseen.php`) never show — those pages only ever render the rounded values.
  `GET /v1/entries`, `GET /v1/entries/search`, and `GET /v1/locations/heatmap` stay fully
  unauthenticated: entries have no equivalent precision concern, and heatmap points are already
  rounded before aggregation.

## Error format

`{ "status": <int>, "message": <string> }`. Simplified from the current `send_json_error()`
shape, which also leaks the internal file path and line number — not appropriate for a public
API contract.

## Migration notes

The API is a clean HTTP contract, not a byte-for-byte passthrough of today's helper signatures —
existing callers need small adaptations when they switch to it:

- **Timestamps are RFC3339 `date-time` strings.** `owntracks.php` currently passes OwnTracks'
  `tst` (Unix epoch seconds) straight to `add_location()`, and `plex.php` passes `date('Y-m-d')`
  (no time component) to `addEntry()`. Both callers must convert to a full `date-time` string
  before calling `POST /v1/locations` / `POST /v1/entries`; the API does not accept epoch
  integers or bare dates.
- **`fulldata_json` is a JSON object over the wire, both directions.** The current
  `lifestream`/`lifestream_locations` columns store it as a JSON *string* (`owntracks.php` calls
  `json_encode($data)` before handing it to `add_location()`), and existing frontend code
  (`htdocs/assets/js/formatting.js`) does `JSON.parse(object.fulldata_json)` on read. Once a
  consumer moves to this API, writers stop pre-encoding and readers drop the `JSON.parse` call —
  the API does the encoding/decoding against the underlying column.
- **`url`/`image` are strings, not `false`.** `plex.php` passes `false` for `addEntry()`'s URL
  argument when there's no URL. Callers migrating to `POST /v1/entries` should omit the field or
  send `""` instead.

## Deliverable

`docs/api/openapi.yaml` — OpenAPI 3.0 spec covering the endpoints above, with request/response
schemas for `Entry`, `Location`, `HeatmapPoint`, and `Error`.

## Future work

Once the API is implemented, it will live in the `Lifestream` Python project, which already has
a pre-commit setup. Wire `redocly lint docs/api/openapi.yaml` (or the equivalent path there) into
that pre-commit config instead of adding a separate hook framework to this repo.
