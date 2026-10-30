<?php

namespace App\Support;

use DateTimeImmutable;

/**
 * A page of the lifestream, parsed from its URL path.
 *
 * Ports the legacy site's path handling (page chrome, year strip, feed
 * query, messages and navigation) into one place. Timestamps are Unix
 * epochs; calendar boundaries (midnight, 03:00) are computed in the app
 * timezone.
 *
 * Back/forward links step by whole calendar periods.
 */
final class FeedPeriod
{
    public const A_DAY = 86400;

    public const A_WEEK = 604800;

    public const RECENT_MAX = 200;

    /**
     * @param  'recent'|'search'|'year'|'week'|'month'|'day'|'anyday'  $kind
     * @param  int|null  $from  Start of the period ("today" in the feed JSON); null for search
     * @param  int|null  $entriesFrom  Inclusive lower bound for entries' date_created
     * @param  int|null  $entriesTo  Exclusive upper bound for entries' date_created
     * @param  int|null  $locationsFrom  Lower bound for the heatmap; null means no heatmap
     * @param  int|null  $locationsTo  Upper bound for the heatmap; null means "until now"
     * @param  array{back: string|false, forward: string|false, up: string|false}  $nav
     */
    private function __construct(
        public readonly string $kind,
        public readonly ?int $from,
        public readonly ?int $entriesFrom,
        public readonly ?int $entriesTo,
        public readonly ?int $locationsFrom,
        public readonly ?int $locationsTo,
        public readonly string $message,
        public readonly string $title,
        public readonly array $nav,
        public readonly int $today,
        public readonly int $datePoint,
        public readonly string $format,
        public readonly string $displayFormat,
        public readonly ?string $search = null,
        public readonly ?int $month = null,
        public readonly ?int $day = null,
    ) {}

    /**
     * Parse a request path such as "/2024/03/05". Returns null for paths the
     * site doesn't recognise (the homepage 404s on these).
     */
    public static function fromPath(string $path, ?int $now = null): ?self
    {
        $now ??= time();

        $split = explode('/', $path);
        if ($split[0] === '') {
            array_shift($split);
        }
        if ($split !== [] && end($split) === '') {
            array_pop($split);
        }

        $count = count($split);

        // Browsers may percent-encode the "*" of "/*/MM/DD" as "%2A".
        if ($count > 0 && $split[0] === '%2A') {
            $split[0] = '*';
        }

        if ($count > 0 && $split[0] === 'search') {
            $term = urldecode($split[1] ?? '');

            return trim($term) === '' ? null : self::search($term, $now);
        }
        if ($count === 0) {
            return self::recent($now);
        }
        if (! ctype_digit($split[0]) && $split[0] !== '*') {
            return null;
        }
        if ($split[0] !== '*' && ! self::validYear((int) $split[0])) {
            return null;
        }

        $year = (int) $split[0];

        if ($count === 1 && $split[0] !== '*') {
            return self::year($year);
        }
        if ($count === 2 && $split[0] !== '*' && preg_match('/^wk(\d+)$/', $split[1], $m)) {
            $week = (int) $m[1];

            return $week >= 1 && $week <= self::weeksInYear($year) ? self::week($week, $year, $m[1]) : null;
        }
        if ($count === 2 && $split[0] !== '*' && ctype_digit($split[1])) {
            $month = (int) $split[1];

            return $month >= 1 && $month <= 12 ? self::month($month, $year) : null;
        }
        if ($count === 3 && ctype_digit($split[1]) && ctype_digit($split[2])) {
            [$month, $day] = [(int) $split[1], (int) $split[2]];

            if ($split[0] === '*') {
                // Any year: allow 29th February.
                return checkdate($month, $day, 2000) ? self::anyDay($month, $day) : null;
            }

            return checkdate($month, $day, $year) ? self::day($month, $day, $year) : null;
        }

        return null;
    }

    /**
     * The year strip shown in the page header: the same point in time in
     * every year from 2001 to now.
     *
     * @return list<array{href: string, title: string, label: int}>
     */
    public function yearLinks(?int $now = null): array
    {
        $now ??= time();
        $thisYear = (int) date('Y', $this->today);
        [$pointYear, $month, $day] = array_map('intval', explode('-', date('Y-n-j', $this->datePoint)));
        $links = [];

        for ($year = 2001; $year <= (int) date('Y', $now); $year++) {
            // Shift by whole years, clamping 29th February to the 28th in
            // non-leap years rather than spilling into March.
            $targetYear = $pointYear + ($year - $thisYear);
            $daysInMonth = (int) date('t', (int) mktime(0, 0, 0, $month, 1, $targetYear));
            $date = (new DateTimeImmutable)->setDate($targetYear, $month, min($day, $daysInMonth))->setTime(0, 0);

            $links[] = [
                'href' => $date->format($this->format),
                'title' => $date->format($this->displayFormat),
                'label' => $year,
            ];
        }

        return $links;
    }

    private static function search(string $term, int $now): self
    {
        // The frontend renders the message as HTML (nicave.js), so escape the
        // user-supplied term; the raw term is kept for the API query.
        $message = sprintf('Search for "%s"', htmlspecialchars($term, ENT_QUOTES, 'UTF-8'));

        return new self(
            kind: 'search',
            from: null,
            entriesFrom: null,
            entriesTo: null,
            locationsFrom: null,
            locationsTo: null,
            message: $message,
            title: $message,
            nav: ['back' => false, 'forward' => false, 'up' => '/'],
            today: $now,
            datePoint: $now,
            format: '/Y/m/d',
            displayFormat: '\Y\e\a\r \o\f Y',
            search: $term,
        );
    }

    /**
     * The homepage: the most recent items.
     */
    public static function recent(?int $now = null): self
    {
        $now ??= time();

        return new self(
            kind: 'recent',
            from: $now,
            entriesFrom: null,
            entriesTo: null,
            locationsFrom: self::at0300($now - self::A_WEEK * 2),
            locationsTo: null,
            message: sprintf('This is the last %d things various services have seen me do.', self::RECENT_MAX),
            title: ' Last 200 Items',
            nav: [
                'back' => date('/Y/m/d', $now),
                'forward' => false,
                'up' => date('/Y/\w\kW', $now),
            ],
            today: $now,
            datePoint: $now,
            format: '/Y/m/d',
            displayFormat: 'l jS F Y',
        );
    }

    private static function year(int $year): self
    {
        $from = (int) mktime(0, 0, 0, 1, 1, $year);
        $next = (int) mktime(0, 0, 0, 1, 1, $year + 1);
        $message = sprintf('Year from %s to %s', date('Y-m-d', $from), date('Y-m-d', $next - 1));

        return new self(
            kind: 'year',
            from: $from,
            entriesFrom: $from,
            entriesTo: $next,
            locationsFrom: $from,
            locationsTo: $next,
            message: $message,
            title: $message,
            nav: [
                'back' => '/'.($year - 1),
                'forward' => '/'.($year + 1),
                'up' => false,
            ],
            today: $from,
            datePoint: $from,
            format: '/Y/m/d',
            displayFormat: '\Y\e\a\r \o\f Y',
        );
    }

    private static function week(int $week, int $year, string $label): self
    {
        [$from, $to] = self::weekBounds($week, $year);
        $message = sprintf('Week %s from %s to %s', $label, date('Y-m-d', $from), date('Y-m-d', $to));
        $back = $week > 1 ? [$year, $week - 1] : [$year - 1, self::weeksInYear($year - 1)];
        $forward = $week < self::weeksInYear($year) ? [$year, $week + 1] : [$year + 1, 1];

        return new self(
            kind: 'week',
            from: $from,
            entriesFrom: $from,
            entriesTo: $to + 1,
            locationsFrom: $from,
            locationsTo: $to + 1,
            message: $message,
            title: $message,
            nav: [
                'back' => sprintf('/%d/wk%02d', ...$back),
                'forward' => sprintf('/%d/wk%02d', ...$forward),
                'up' => date('/Y/m', $from),
            ],
            today: $from,
            datePoint: intdiv($to - $from, 2) + $from,
            format: '/Y/\w\kW',
            displayFormat: '\W\k W Y',
        );
    }

    private static function month(int $month, int $year): self
    {
        $from = (int) mktime(0, 0, 0, $month, 1, $year);
        $next = (int) mktime(0, 0, 0, $month + 1, 1, $year);
        $message = sprintf('Month from %s to %s', date('Y-m-d', $from), date('Y-m-d', $next - 1));

        return new self(
            kind: 'month',
            from: $from,
            entriesFrom: $from,
            entriesTo: $next,
            locationsFrom: $from,
            locationsTo: $next,
            message: $message,
            title: $message,
            nav: [
                'back' => date('/Y/m', (int) mktime(0, 0, 0, $month - 1, 1, $year)),
                'forward' => date('/Y/m', $next),
                'up' => date('/Y', $from),
            ],
            today: $from,
            datePoint: $from,
            format: '/Y/m',
            displayFormat: 'F Y',
        );
    }

    /**
     * A single day. Days run 03:00 to 03:00, so late nights count as the
     * day before.
     */
    private static function day(int $month, int $day, int $year): self
    {
        $from = (int) mktime(0, 0, 0, $month, $day, $year);
        $start = (int) mktime(3, 0, 0, $month, $day, $year);
        $end = (int) mktime(3, 0, 0, $month, $day + 1, $year);
        $message = sprintf('Day from %s to %s', date('Y-m-d H:i', $start), date('Y-m-d H:i', $end));

        return new self(
            kind: 'day',
            from: $from,
            entriesFrom: $start,
            entriesTo: $end,
            locationsFrom: $start,
            locationsTo: $end,
            message: $message,
            title: $message,
            nav: [
                'back' => date('/Y/m/d', (int) mktime(0, 0, 0, $month, $day - 1, $year)),
                'forward' => date('/Y/m/d', (int) mktime(0, 0, 0, $month, $day + 1, $year)),
                'up' => date('/Y/\w\kW', $from),
            ],
            today: $from,
            datePoint: $from,
            format: '/Y/m/d',
            displayFormat: 'l jS F Y',
        );
    }

    /**
     * The same calendar day in every year ("/ * /MM/DD"). The legacy app
     * anchored this to year 2000 (mktime() of intval('*')); that only
     * affects the "Day from" message and the year strip.
     */
    private static function anyDay(int $month, int $day): self
    {
        $from = (int) mktime(0, 0, 0, $month, $day, 2000);
        $message = sprintf('Day from %s to %s', date('Y-m-d 03:00', $from), date('Y-m-d 03:00', (int) mktime(0, 0, 0, $month, $day + 1, 2000)));

        return new self(
            kind: 'anyday',
            from: $from,
            entriesFrom: null,
            entriesTo: null,
            locationsFrom: null,
            locationsTo: null,
            message: $message,
            title: $message,
            nav: [
                'back' => date('/\*/m/d', (int) mktime(0, 0, 0, $month, $day - 1, 2000)),
                'forward' => date('/\*/m/d', (int) mktime(0, 0, 0, $month, $day + 1, 2000)),
                'up' => '/',
            ],
            today: $from,
            datePoint: $from,
            format: '/\*/m/d',
            displayFormat: 'l jS F \A\n\y \y\e\a\r',
            month: $month,
            day: $day,
        );
    }

    /**
     * Port of the legacy get_start_and_end_date_from_week(): weeks run from
     * the Sunday before to the following Saturday, counted from 4th January.
     *
     * @return array{int, int}
     */
    public static function weekBounds(int $week, int $year): array
    {
        $day = 4 + ($week - 1) * 7;
        $dayOfWeek = (int) date('N', (int) mktime(0, 0, 0, 1, $day, $year));

        $from = (int) mktime(0, 0, 0, 1, $day - $dayOfWeek, $year);
        $to = (int) mktime(0, 0, 0, 1, $day + 7 - $dayOfWeek, $year) - 1;

        return [$from, $to];
    }

    private static function weeksInYear(int $year): int
    {
        return (int) date('W', (int) mktime(0, 0, 0, 12, 28, $year));
    }

    private static function validYear(int $year): bool
    {
        return $year >= 1970 && $year <= 2100;
    }

    private static function at0300(int $timestamp): int
    {
        return (int) mktime(3, 0, 0, (int) date('n', $timestamp), (int) date('j', $timestamp), (int) date('Y', $timestamp));
    }

    public function isFuture(?int $now = null): bool
    {
        return $this->from !== null && $this->from > ($now ?? time());
    }
}
