<?php

namespace App\Http\Controllers;

use App\Services\LifestreamApi;
use App\Support\FeedPeriod;
use App\Support\LocationIcon;
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
     * The last 30 days, or a calendar year with ?year=YYYY.
     */
    public function heatmap(Request $request): View
    {
        return $this->map($this->year($request), 30, 20);
    }

    /**
     * The last 24 hours, or a calendar year with ?year=YYYY (as on /maps,
     * but drawn with smaller heatmap points).
     */
    public function today(Request $request): View
    {
        return $this->map($this->year($request), 1, 12);
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
        if (($icon = LocationIcon::url($location['icon'] ?? null)) !== null) {
            $entry['icon'] = $icon;
        }

        return response()->json([$entry]);
    }

    private function map(?int $year, int $days, int $heatmapRadius): View
    {
        if ($year !== null) {
            $from = $this->toDate((int) mktime(0, 0, 0, 1, 1, $year));
            $to = $this->toDate((int) mktime(0, 0, 0, 1, 1, $year + 1));
        } else {
            $to = $this->toDate(time());
            $from = $this->toDate(time() - FeedPeriod::A_DAY * $days);
        }

        $points = $this->api->locations($from, $to);

        $title = 'Heatmap of locations'.($year !== null ? ' for '.$year : ' last '.$days.' days');

        return view('map', [
            'title' => $title,
            'locations' => $this->collapse($points),
            'heatmapRadius' => $heatmapRadius,
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
                if (($icon = LocationIcon::url($point['icon'] ?? null)) !== null) {
                    $entry['icon'] = $icon;
                }
                $locations[] = $entry;
            }

            $previous = $key;
        }

        return $locations;
    }

    /**
     * ?year=, limited to the years the lifestream covers; anything else
     * means "no year" (the rolling window).
     */
    private function year(Request $request): ?int
    {
        $year = $request->integer('year');

        return $year >= 2001 && $year <= (int) date('Y') ? $year : null;
    }

    private function toDate(int $timestamp): DateTimeImmutable
    {
        return (new DateTimeImmutable('@'.$timestamp))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }

    private function formatTimestamp(mixed $timestamp): string
    {
        return str_replace('T', ' ', (string) $timestamp);
    }
}
