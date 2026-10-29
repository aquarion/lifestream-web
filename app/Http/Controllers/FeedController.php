<?php

namespace App\Http\Controllers;

use App\Services\LifestreamApi;
use App\Services\LifestreamApiException;
use App\Support\FeedPeriod;
use App\Support\LocationIcon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The JSON feed behind the homepage tiles (POST /feed).
 *
 * Consumed by resources/js/lifestream/feed.js. The response shape follows the
 * pre-Laravel fetchNext.php; API failures are rendered as a JSON 503 by the
 * handler in bootstrap/app.php.
 */
class FeedController extends Controller
{
    private const BLOCK_SIZE = 100;

    /** Seconds the frontend waits before polling for new items. */
    private const POLL_SECONDS = 30;

    /** Seconds the frontend waits before fetching the next page of a period. */
    private const NEXT_PAGE_SECONDS = 2;

    /**
     * Off-site images are loaded through this caching proxy on art.istic.net,
     * so the page doesn't hotlink (and break on) third-party image URLs.
     */
    private const IMAGE_PROXY = 'https://art.istic.net/lifestreamprox/?url=';

    public function __construct(private readonly LifestreamApi $api) {}

    public function __invoke(Request $request): JsonResponse
    {
        // Unrecognised paths fall back to the homepage feed, as the legacy
        // endpoint did; the page itself has already 404ed on them.
        $period = FeedPeriod::fromPath((string) $request->input('path', '/'))
            ?? FeedPeriod::recent();

        $after = $request->filled('after') ? (string) $request->input('after') : null;
        $offset = max(0, (int) $request->input('offset', 0));

        $return = [
            'status' => 200,
            'next' => self::POLL_SECONDS,
            'today' => $period->from ?? false,
            'offset' => 0,
            'max' => 0,
            'direction' => $after !== null ? 'prepend' : 'append',
            'message' => $period->message,
            'title' => $period->title,
            'items' => [],
            'nav' => $period->nav,
        ];

        if ($period->isFuture()) {
            $return['items'][] = [
                'source' => 'xkcd',
                'title' => '<h2>That is the future. We will do things differently there.</h2>
				<a href="https://xkcd.com/338/"><img src=\'https://imgs.xkcd.com/comics/future.png\'></a>',
                'image' => '',
                'date_created' => time(),
            ];

            return response()->json($return);
        }

        $page = $this->fetchEntries($period, $offset, $after);

        $return['max'] = $page['total'];
        $max = $period->kind === 'recent' ? FeedPeriod::RECENT_MAX : null;

        if ($return['max'] > $offset + self::BLOCK_SIZE) {
            $return['offset'] = $offset + self::BLOCK_SIZE;
            $return['next'] = self::NEXT_PAGE_SECONDS;

            $percent = ($return['offset'] / ($max ?? $return['max'])) * 100;
            $return['message'] .= sprintf(' (%d%% Loaded)', $percent);
        }

        if ($max !== null && $return['offset'] >= $max) {
            $return['offset'] = 0;
        }

        $return['items'] = array_map($this->formatEntry(...), $page['items']);

        if ($period->locationsFrom !== null && $offset === 0) {
            $return['locations'] = $this->locations($period);
        }

        return response()->json($return);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, offset: int, limit: int}
     */
    private function fetchEntries(FeedPeriod $period, int $offset, ?string $after): array
    {
        if ($period->kind === 'search') {
            return $this->api->search((string) $period->search, $offset, self::BLOCK_SIZE);
        }

        return $this->api->entries(
            from: $this->toDate($period->entriesFrom),
            to: $this->toDate($period->entriesTo),
            offset: $offset,
            limit: self::BLOCK_SIZE,
            after: $after,
            order: $period->kind === 'recent' ? 'desc' : 'asc',
            month: $period->month,
            day: $period->day,
        );
    }

    /**
     * Shape an API entry for the frontend: empty strings rather than nulls for
     * url/image, off-site images proxied, and art.istic.net images on HTTPS
     * (older rows store http:// URLs, which the page would load as mixed
     * content).
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function formatEntry(array $entry): array
    {
        $entry['url'] = $entry['url'] ?? '';
        // Some importers stored "0" for "no image".
        $entry['image'] = in_array($entry['image'] ?? '', ['', '0'], true) ? '' : $entry['image'];

        if ($entry['image'] !== '') {
            $entry['image'] = parse_url((string) $entry['image'], PHP_URL_HOST) === 'art.istic.net'
                ? (string) preg_replace('#^http://#', 'https://', (string) $entry['image'])
                : self::IMAGE_PROXY.urlencode((string) $entry['image']);
        }

        return $entry;
    }

    /**
     * @return list<array{lat: float, long: float, title: string, value: int, icon?: string}>
     */
    private function locations(FeedPeriod $period): array
    {
        try {
            $points = $this->api->heatmap(
                $this->toDate($period->locationsFrom) ?? now(),
                $this->toDate($period->locationsTo),
            );
        } catch (LifestreamApiException $e) {
            // The feed is still useful without its map.
            report($e);

            return [];
        }

        $locations = [];
        foreach ($points as $point) {
            // 0,0 is a bogus fix, not a real location.
            if ($point['lat'] == 0 && $point['long'] == 0) {
                continue;
            }

            $location = [
                'lat' => $point['lat'],
                'long' => $point['long'],
                'title' => $point['title'] ?? '',
                'value' => $point['count'],
            ];
            if (($icon = LocationIcon::url($point['icon'] ?? null)) !== null) {
                $location['icon'] = $icon;
            }
            $locations[] = $location;
        }

        return $locations;
    }

    private function toDate(?int $timestamp): ?\DateTimeImmutable
    {
        return $timestamp === null ? null : (new \DateTimeImmutable('@'.$timestamp))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    }
}
