<?php

namespace App\Http\Controllers;

use App\Services\LifestreamApi;
use App\Services\LifestreamApiException;
use App\Support\FeedPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The JSON feed behind the homepage tiles (legacy fetchNext.php).
 *
 * The response shape is kept identical to the legacy endpoint so the
 * existing public/assets/js/nicave.js keeps working unchanged.
 */
class FeedController extends Controller
{
    private const BLOCK_SIZE = 100;

    private const IMAGE_PROXY = 'http://art.istic.net/lifestreamprox/?url=';

    public function __construct(private readonly LifestreamApi $api) {}

    public function __invoke(Request $request): JsonResponse
    {
        $period = FeedPeriod::fromPath((string) $request->input('path', '/'))
            ?? FeedPeriod::fromPath('/');
        assert($period !== null);

        $after = $request->filled('after') ? (string) $request->input('after') : null;
        $offset = max(0, (int) $request->input('offset', 0));

        $return = [
            'status' => 200,
            'next' => 30,
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
				<a href="http://xkcd.com/338/"><img src=\'http://imgs.xkcd.com/comics/future.png\'></a>',
                'image' => '',
                'date_created' => time(),
            ];

            return response()->json($return);
        }

        try {
            $page = $this->fetchEntries($period, $offset, $after);
        } catch (LifestreamApiException $e) {
            report($e);

            return response()->json(['response' => 500, 'message' => 'Could not load the lifestream right now.'], 500);
        }

        $return['max'] = $page['total'];
        $max = $period->kind === 'recent' ? FeedPeriod::RECENT_MAX : null;

        if ($return['max'] > $offset + self::BLOCK_SIZE) {
            $return['offset'] = $offset + self::BLOCK_SIZE;
            $return['next'] = 2;

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
     * Shape an API entry like the legacy database row the frontend expects:
     * fulldata_json as a JSON string (formatting.js JSON.parse()s it), empty
     * strings rather than nulls for url/image, and off-site images proxied.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function formatEntry(array $entry): array
    {
        $entry['url'] = $entry['url'] ?? '';
        $entry['image'] = $entry['image'] ?? '';

        if ($entry['image'] !== '' && parse_url((string) $entry['image'], PHP_URL_HOST) !== 'art.istic.net') {
            $entry['image'] = self::IMAGE_PROXY.urlencode((string) $entry['image']);
        }

        if (isset($entry['fulldata_json'])) {
            $entry['fulldata_json'] = json_encode($entry['fulldata_json']);
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
            if (! $point['lat'] || ! $point['long']) {
                continue;
            }

            $location = [
                'lat' => $point['lat'],
                'long' => $point['long'],
                'title' => $point['title'] ?? '',
                'value' => $point['count'],
            ];
            if (! empty($point['icon'])) {
                $location['icon'] = $point['icon'];
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
