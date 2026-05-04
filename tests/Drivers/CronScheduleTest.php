<?php

use Carbon\Carbon;
use DirectoryTree\Cadence\Drivers\CronSchedule;

it('computes the next occurrence', function () {
    Carbon::setTestNow('2026-05-02 10:00:00');

    $next = (new CronSchedule('0 12 * * *'))->getNextOccurrence(now());

    expect($next->format('Y-m-d H:i:s'))->toBe('2026-05-02 12:00:00');
});

it('computes the next occurrence when current time matches', function () {
    Carbon::setTestNow('2026-05-02 12:00:00');

    $next = (new CronSchedule('0 12 * * *'))->getNextOccurrence(now());

    expect($next->format('Y-m-d H:i:s'))->toBe('2026-05-03 12:00:00');
});

it('computes the next occurrence for a weekly schedule', function () {
    // Saturday
    Carbon::setTestNow('2026-05-02 10:00:00');

    // Every Monday at 9am
    $next = (new CronSchedule('0 9 * * 1'))->getNextOccurrence(now());

    expect($next->format('Y-m-d H:i:s'))->toBe('2026-05-04 09:00:00');
});

it('computes the next occurrence with a timezone', function () {
    Carbon::setTestNow('2026-05-02 12:00:00', 'UTC');

    $next = (new CronSchedule('0 9 * * *', 'America/New_York'))->getNextOccurrence(now());

    // 12:00 UTC = 8:00 AM ET, so next 9am ET = 13:00 UTC
    expect($next->timezone('UTC')->format('Y-m-d H:i:s'))->toBe('2026-05-02 13:00:00');
});

it('computes the next occurrence with timezone set via method', function () {
    Carbon::setTestNow('2026-05-02 12:00:00', 'UTC');

    $next = (new CronSchedule('0 9 * * *'))
        ->setTimezone('America/New_York')
        ->getNextOccurrence(now());

    expect($next->timezone('UTC')->format('Y-m-d H:i:s'))->toBe('2026-05-02 13:00:00');
});

it('serializes to expression', function () {
    expect((new CronSchedule('0 9 * * 1'))->toExpression())->toBe('0 9 * * 1');
});

it('reconstitutes from expression', function () {
    Carbon::setTestNow('2026-05-02 10:00:00');

    $next = CronSchedule::fromExpression('0 9 * * 1')->getNextOccurrence(now());

    expect($next->format('Y-m-d H:i:s'))->toBe('2026-05-04 09:00:00');
});

it('returns the timezone', function () {
    expect((new CronSchedule('* * * * *'))->getTimezone())->toBeNull();
    expect((new CronSchedule('* * * * *', 'America/New_York'))->getTimezone())->toBe('America/New_York');
});

it('returns a carbon instance', function () {
    $next = (new CronSchedule('0 12 * * *'))->getNextOccurrence(now());

    expect($next)->toBeInstanceOf(Carbon::class);
});
