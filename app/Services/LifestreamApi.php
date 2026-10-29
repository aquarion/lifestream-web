<?php

namespace App\Services;

use DateTimeInterface;
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
        ], fn ($value) => $value !== null);

        return $this->get('/entries', $query);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, offset: int, limit: int}
     */
    public function search(string $q, int $offset = 0, int $limit = 100): array
    {
        return $this->get('/entries/search', ['q' => $q, 'offset' => $offset, 'limit' => $limit]);
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

        if ($response->status() === 404) {
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
            return $this->request()->get(ltrim($path, '/'), $query);
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
