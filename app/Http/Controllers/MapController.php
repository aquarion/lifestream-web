<?php

namespace App\Http\Controllers;

use App\Services\LifestreamApi;
use App\Support\FeedPeriod;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Location heatmap pages (legacy maps.php, today.php) and the "last seen"
 * JSON (legacy lastseen.php).
 *
 * These only ever receive rounded (`*_vague`) coordinates: the API redacts
 * precise positions for unauthenticated callers.
 */
class MapController extends Controller
{
    public function __construct(private readonly LifestreamApi $api) {}

    /**
     * The last 30 days, or a whole year with ?year=YYYY.
     */
    public function heatmap(Request $request): View
    {
        $year = $request->integer('year') ?: null;

        return $this->map($year, 30, 5000);
    }

    /**
     * The last day.
     */
    public function today(Request $request): View
    {
        $year = $request->integer('year') ?: null;

        return $this->map($year, 1, 50);
    }

    public function lastSeen(): JsonResponse
    {
        $location = $this->api->latestLocation();

        if ($location === null) {
            return response()->json([]);
        }

        $entry = [
            'lat' => $location['lat_vague'],
            'long' => $location['long_vague'],
            'title' => $location['title'] ?: $this->formatTimestamp($location['timestamp']),
        ];
        if (! empty($location['icon'])) {
            $entry['icon'] = $location['icon'];
        }

        return response()->json([$entry]);
    }

    private function map(?int $year, int $days, int $heatmapSize): View
    {
        if ($year !== null) {
            $to = (int) mktime(23, 59, 59, 12, 31, $year);
            $from = $to - FeedPeriod::A_YEAR;
        } else {
            $to = time();
            $from = $to - FeedPeriod::A_DAY * $days;
        }

        $points = $this->api->locations($this->midnight($from), $this->midnight($to));

        $title = 'Heatmap of locations'.($year !== null ? ' for '.$year : ' last '.$days.' days');

        return view('map', [
            'title' => $title,
            'locations' => $this->collapse($points),
            'heatmapSize' => $heatmapSize,
        ]);
    }

    /**
     * Drop consecutive points at the same rounded position.
     *
     * @param  list<array<string, mixed>>  $points
     * @return list<array<string, mixed>>
     */
    private function collapse(array $points): array
    {
        $locations = [];
        $previous = null;

        foreach ($points as $point) {
            if ($point['lat_vague'] === null || $point['long_vague'] === null) {
                continue;
            }

            $key = $point['lat_vague'].'/'.$point['long_vague'];
            $timestamp = $this->formatTimestamp($point['timestamp']);

            if ($key !== $previous) {
                $entry = [
                    'lat' => $point['lat_vague'],
                    'long' => $point['long_vague'],
                    'title' => $point['title'] ? $point['title'].' - '.$timestamp : $timestamp,
                    'source' => $point['source'],
                ];
                if (! empty($point['icon'])) {
                    $entry['icon'] = $point['icon'];
                }
                $locations[] = $entry;
            }

            $previous = $key;
        }

        return $locations;
    }

    private function midnight(int $timestamp): DateTimeImmutable
    {
        return (new DateTimeImmutable('@'.$timestamp))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->setTime(0, 0);
    }

    private function formatTimestamp(mixed $timestamp): string
    {
        return str_replace('T', ' ', (string) $timestamp);
    }
}
