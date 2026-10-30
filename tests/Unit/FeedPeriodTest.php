<?php

use App\Support\FeedPeriod;

beforeEach(function () {
    $this->previousTimezone = date_default_timezone_get();
    date_default_timezone_set('Europe/London');
});

afterEach(function () {
    date_default_timezone_set($this->previousTimezone);
});

it('parses a year', function () {
    $period = FeedPeriod::fromPath('/2019');

    expect($period->kind)->toBe('year')
        ->and(date('Y-m-d H:i', $period->entriesFrom))->toBe('2019-01-01 00:00')
        ->and(date('Y-m-d H:i', $period->entriesTo))->toBe('2020-01-01 00:00')
        ->and($period->message)->toBe('Year from 2019-01-01 to 2019-12-31')
        ->and($period->nav)->toBe(['back' => '/2018', 'forward' => '/2020', 'up' => false]);
});

it('parses a month', function () {
    $period = FeedPeriod::fromPath('/2019/03/');

    expect($period->kind)->toBe('month')
        ->and(date('Y-m-d H:i', $period->entriesFrom))->toBe('2019-03-01 00:00')
        ->and(date('Y-m-d H:i', $period->entriesTo))->toBe('2019-04-01 00:00')
        ->and($period->message)->toBe('Month from 2019-03-01 to 2019-03-31')
        ->and($period->nav)->toBe(['back' => '/2019/02', 'forward' => '/2019/04', 'up' => '/2019']);
});

it('steps months across year boundaries', function () {
    expect(FeedPeriod::fromPath('/2019/12')->nav)->toBe(['back' => '/2019/11', 'forward' => '/2020/01', 'up' => '/2019'])
        ->and(FeedPeriod::fromPath('/2019/01')->nav['back'])->toBe('/2018/12');
});

it('parses a week as Sunday to Saturday', function () {
    $period = FeedPeriod::fromPath('/2019/wk12');

    expect($period->kind)->toBe('week')
        ->and(date('D Y-m-d H:i', $period->entriesFrom))->toBe('Sun 2019-03-17 00:00')
        ->and(date('D Y-m-d H:i', $period->entriesTo))->toBe('Sun 2019-03-24 00:00')
        ->and($period->message)->toBe('Week 12 from 2019-03-17 to 2019-03-23')
        ->and($period->nav)->toBe(['back' => '/2019/wk11', 'forward' => '/2019/wk13', 'up' => '/2019/03']);
});

it('steps weeks across year boundaries', function () {
    $first = FeedPeriod::fromPath('/2019/wk01');
    $last = FeedPeriod::fromPath('/2020/wk53');

    expect($first->message)->toBe('Week 01 from 2018-12-30 to 2019-01-05')
        ->and($first->nav['back'])->toBe('/2018/wk52')
        ->and($last->message)->toBe('Week 53 from 2020-12-27 to 2021-01-02')
        ->and($last->nav['forward'])->toBe('/2021/wk01');
});

it('parses a day running from 03:00 to 03:00', function () {
    $period = FeedPeriod::fromPath('/2019/03/05');

    expect($period->kind)->toBe('day')
        ->and(date('Y-m-d H:i', $period->entriesFrom))->toBe('2019-03-05 03:00')
        ->and(date('Y-m-d H:i', $period->entriesTo))->toBe('2019-03-06 03:00')
        ->and($period->message)->toBe('Day from 2019-03-05 03:00 to 2019-03-06 03:00')
        ->and($period->nav)->toBe(['back' => '/2019/03/04', 'forward' => '/2019/03/06', 'up' => '/2019/wk10']);
});

it('keeps day boundaries at 03:00 across a DST change', function () {
    $period = FeedPeriod::fromPath('/2019/10/27');

    expect(date('Y-m-d H:i', $period->entriesFrom))->toBe('2019-10-27 03:00')
        ->and(date('Y-m-d H:i', $period->entriesTo))->toBe('2019-10-28 03:00')
        ->and($period->nav['forward'])->toBe('/2019/10/28');
});

it('parses the same day in any year as month and day', function () {
    $period = FeedPeriod::fromPath('/*/03/05');

    expect($period->kind)->toBe('anyday')
        ->and($period->month)->toBe(3)
        ->and($period->day)->toBe(5)
        ->and($period->entriesFrom)->toBeNull()
        ->and($period->locationsFrom)->toBeNull()
        ->and($period->nav)->toBe(['back' => '/*/03/04', 'forward' => '/*/03/06', 'up' => '/']);
});

it('escapes the search term in the message but not the query', function () {
    $period = FeedPeriod::fromPath('/search/%3Cimg%20src%3Dx%20onerror%3Dalert(1)%3E');

    expect($period->search)->toBe('<img src=x onerror=alert(1)>')
        ->and($period->message)->toBe('Search for "&lt;img src=x onerror=alert(1)&gt;"')
        ->and($period->title)->toBe($period->message);
});

it('accepts a percent-encoded * in the any-year path', function () {
    $period = FeedPeriod::fromPath('/%2A/03/05');

    expect($period->kind)->toBe('anyday')
        ->and($period->month)->toBe(3)
        ->and($period->day)->toBe(5);
});

it('parses a search', function () {
    $period = FeedPeriod::fromPath('/search/hello%20world');

    expect($period->kind)->toBe('search')
        ->and($period->search)->toBe('hello world')
        ->and($period->from)->toBeNull()
        ->and($period->title)->toBe('Search for "hello world"')
        ->and($period->nav)->toBe(['back' => false, 'forward' => false, 'up' => '/']);
});

it('defaults to the most recent items', function () {
    $now = mktime(12, 0, 0, 3, 5, 2019);
    $period = FeedPeriod::fromPath('/', $now);

    expect($period->kind)->toBe('recent')
        ->and($period->title)->toBe(' Last 200 Items')
        ->and($period->entriesFrom)->toBeNull()
        ->and(date('Y-m-d H:i', $period->locationsFrom))->toBe('2019-02-19 03:00')
        ->and($period->nav)->toBe(['back' => '/2019/03/05', 'forward' => false, 'up' => '/2019/wk10']);
});

it('rejects paths it does not recognise', function (string $path) {
    expect(FeedPeriod::fromPath($path))->toBeNull();
})->with([
    '/2019/03/05/extra', '/nonsense', '/2019/march', '/wp-admin/',
    '/2019/13', '/2019/00', '/2019/13/45', '/2019/02/29', '/2019/wk00', '/2019/wk53', '/2019/wk99',
    '/1900', '/99999', '/*/02/30', '/*', '/*/03', '/search', '/search/', '/search/%20',
]);

it('accepts leap days and 53-week years', function (string $path) {
    expect(FeedPeriod::fromPath($path))->not->toBeNull();
})->with(['/2020/02/29', '/*/02/29', '/2020/wk53']);

it('clamps leap days in the year strip', function () {
    $links = FeedPeriod::fromPath('/2020/02/29')->yearLinks(mktime(0, 0, 0, 1, 1, 2021));

    expect($links[18]['href'])->toBe('/2019/02/28')
        ->and($links[19]['href'])->toBe('/2020/02/29')
        ->and($links[20]['href'])->toBe('/2021/02/28');
});

it('links to the same day in every year', function () {
    $links = FeedPeriod::fromPath('/2019/03/05')->yearLinks(mktime(0, 0, 0, 1, 1, 2021));

    expect($links)->toHaveCount(21)
        ->and($links[0])->toBe(['href' => '/2001/03/05', 'title' => 'Monday 5th March 2001', 'label' => 2001])
        ->and($links[20])->toBe(['href' => '/2021/03/05', 'title' => 'Friday 5th March 2021', 'label' => 2021]);
});

it('labels the year strip for a year page', function () {
    $links = FeedPeriod::fromPath('/2019')->yearLinks(mktime(0, 0, 0, 1, 1, 2020));

    expect($links[18])->toBe(['href' => '/2019/01/01', 'title' => 'Year of 2019', 'label' => 2019]);
});

it('labels the year strip for an any-year page', function () {
    $links = FeedPeriod::fromPath('/*/03/05')->yearLinks(mktime(0, 0, 0, 1, 1, 2020));

    expect($links[0])->toBe(['href' => '/*/03/05', 'title' => 'Monday 5th March Any year', 'label' => 2001]);
});

it('detects future periods', function () {
    $now = mktime(0, 0, 0, 1, 1, 2020);

    expect(FeedPeriod::fromPath('/2030')->isFuture($now))->toBeTrue()
        ->and(FeedPeriod::fromPath('/2019')->isFuture($now))->toBeFalse()
        ->and(FeedPeriod::fromPath('/search/x')->isFuture($now))->toBeFalse();
});

it('keeps week boundaries at midnight across a DST change', function () {
    [$from, $to] = FeedPeriod::weekBounds(14, 2019);

    expect(date('D Y-m-d H:i:s', $from))->toBe('Sun 2019-03-31 00:00:00')
        ->and(date('D Y-m-d H:i:s', $to))->toBe('Sat 2019-04-06 23:59:59');
});
