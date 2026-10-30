<?php

namespace App\Services;

use DateTimeInterface;
use GuzzleHttp\Psr7\Query;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Client for the Lifestream data API (aquarion/lifestream, `/v1`).
 *
 * This is the app's only data source: nothing here talks to the lifestream
 * database directly.
 */
class LifestreamApi
{
    /**
     * Datetimes are sent as naive wall-clock strings in the app timezone,
     * matching how the legacy app compared against the database's naive
     * DATETIME columns.
     */
    private const DATE_FORMAT = 'Y-m-d\TH:i:s';

    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 5,
    ) {}

    /**
     * @param  'asc'|'desc'  $order
     * @param  list<string>  $excludeSources  Sources to leave out; the API excludes none by default.
     * @return array{items: list<array<string, mixed>>, total: int, offset: int, limit: int}
     */
    public function entries(
        ?DateTimeInterface $from = null,
        ?DateTimeInterface $to = null,
        int $offset = 0,
        int $limit = 100,
        ?string $after = null,
        string $order = 'asc',
        ?int $month = null,
        ?int $day = null,
        array $excludeSources = [],
    ): array {
        $query = array_filter([
            'from' => $this->formatDate($from),
            'to' => $this->formatDate($to),
            'after' => $after,
            'offset' => $offset,
            'limit' => $limit,
            'order' => $order === 'asc' ? null : $order,
            'month' => $month,
            'day' => $day,
            'exclude_source' => $excludeSources ?: null,
        ], fn ($value) => $value !== null);

        return $this->get('/entries', $query);
    }

    /**
     * @param  list<string>  $excludeSources  Sources to leave out; the API excludes none by default.
     * @return array{items: list<array<string, mixed>>, total: int, offset: int, limit: int}
     */
    public function search(string $q, int $offset = 0, int $limit = 100, array $excludeSources = []): array
    {
        return $this->get('/entries/search', array_filter([
            'q' => $q,
            'offset' => $offset,
            'limit' => $limit,
            'exclude_source' => $excludeSources ?: null,
        ], fn ($value) => $value !== null));
    }

    /**
     * @return list<array{lat: float, long: float, count: int, title: ?string, icon: ?string}>
     */
    public function heatmap(DateTimeInterface $from, ?DateTimeInterface $to = null, ?string $source = null): array
    {
        return $this->get('/locations/heatmap', array_filter([
            'from' => $this->formatDate($from),
            'to' => $this->formatDate($to),
            'source' => $source,
        ], fn ($value) => $value !== null));
    }

    /**
     * Location points in a time range. Unauthenticated, so precise lat/long
     * are always null and only the rounded `*_vague` values are present.
     *
     * @return list<array<string, mixed>>
     */
    public function locations(DateTimeInterface $from, ?DateTimeInterface $to = null): array
    {
        return $this->get('/locations', array_filter([
            'from' => $this->formatDate($from),
            'to' => $this->formatDate($to),
        ], fn ($value) => $value !== null));
    }

    /**
     * @return array<string, mixed>|null Null when no location has been recorded.
     */
    public function latestLocation(): ?array
    {
        $response = $this->send('/locations/latest', []);

        // The API answers 404 with its own `detail` when no location has been
        // recorded. Any other 404 is an error: a wrong base URL gets
        // FastAPI's generic {"detail": "Not Found"}, or a non-JSON page.
        $detail = $response->json('detail');
        if ($response->status() === 404 && is_string($detail) && $detail !== 'Not Found') {
            return null;
        }

        return $this->decode($response, '/locations/latest');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     */
    private function get(string $path, array $query): array
    {
        return $this->decode($this->send($path, $query), $path);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function send(string $path, array $query): Response
    {
        try {
            // Query::build repeats a key for each value of a list
            // (exclude_source=a&exclude_source=b), which is how FastAPI reads
            // list params; Laravel's default would send exclude_source[0]=a.
            return $this->request()->get(ltrim($path, '/'), Query::build($query));
        } catch (ConnectionException $e) {
            throw new LifestreamApiException("Lifestream API unreachable: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * @return array<mixed>
     */
    private function decode(Response $response, string $path): array
    {
        if (! $response->successful()) {
            $message = $response->json('message') ?? $response->json('detail') ?? $response->reason();

            throw new LifestreamApiException(
                sprintf('Lifestream API %s returned %d: %s', $path, $response->status(), is_string($message) ? $message : json_encode($message)),
                $response->status(),
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new LifestreamApiException("Lifestream API {$path} returned a non-JSON body");
        }

        return $data;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/').'/')
            ->acceptJson()
            ->timeout($this->timeout);
    }

    private function formatDate(?DateTimeInterface $date): ?string
    {
        return $date?->format(self::DATE_FORMAT);
    }
}
