<?php

use Carbon\Carbon;
use DirectoryTree\Cadence\Drivers\RecurrSchedule;

it('computes the next daily occurrence', function () {
    Carbon::setTestNow('2026-05-02 10:00:00');

    $next = (new RecurrSchedule('FREQ=DAILY;DTSTART=20260501T090000'))->getNextOccurrence(now());

    expect($next->format('Y-m-d H:i:s'))->toBe('2026-05-03 09:00:00');
});

it('computes the next weekly occurrence', function () {
    // Saturday
    Carbon::setTestNow('2026-05-02 10:00:00');

    $next = (new RecurrSchedule('FREQ=WEEKLY;BYDAY=MO;DTSTART=20260427T090000'))->getNextOccurrence(now());

    // Next Monday
    expect($next->format('Y-m-d H:i:s'))->toBe('2026-05-04 09:00:00');
});

it('computes the next monthly occurrence', function () {
    Carbon::setTestNow('2026-05-15 10:00:00');

    $next = (new RecurrSchedule('FREQ=MONTHLY;BYMONTHDAY=1;DTSTART=20260101T090000'))->getNextOccurrence(now());

    expect($next->format('Y-m-d H:i:s'))->toBe('2026-06-01 09:00:00');
});

it('returns null when rule is exhausted', function () {
    Carbon::setTestNow('2026-06-01 10:00:00');

    $next = (new RecurrSchedule('FREQ=DAILY;COUNT=3;DTSTART=20260501T090000'))->getNextOccurrence(now());

    expect($next)->toBeNull();
});

it('computes next occurrence with timezone', function () {
    Carbon::setTestNow('2026-05-02 12:00:00', 'UTC');

    $next = (new RecurrSchedule('FREQ=DAILY;DTSTART=20260501T090000', 'America/New_York'))->getNextOccurrence(now());

    // 12:00 UTC = 8:00 AM ET, so next 9am ET = 13:00 UTC
    expect($next->timezone('UTC')->format('Y-m-d H:i:s'))->toBe('2026-05-02 13:00:00');
});

it('computes next occurrence with timezone set via method', function () {
    Carbon::setTestNow('2026-05-02 12:00:00', 'UTC');

    $next = (new RecurrSchedule('FREQ=DAILY;DTSTART=20260501T090000'))
        ->setTimezone('America/New_York')
        ->getNextOccurrence(now());

    expect($next->timezone('UTC')->format('Y-m-d H:i:s'))->toBe('2026-05-02 13:00:00');
});

it('serializes to expression', function () {
    expect((new RecurrSchedule('FREQ=DAILY;COUNT=5'))->toExpression())->toBe('FREQ=DAILY;COUNT=5');
});

it('reconstitutes from expression', function () {
    Carbon::setTestNow('2026-05-02 10:00:00');

    $next = RecurrSchedule::fromExpression('FREQ=DAILY;DTSTART=20260501T090000')->getNextOccurrence(now());

    expect($next->format('Y-m-d H:i:s'))->toBe('2026-05-03 09:00:00');
});

it('returns the timezone', function () {
    expect((new RecurrSchedule('FREQ=DAILY'))->getTimezone())->toBeNull();
    expect((new RecurrSchedule('FREQ=DAILY', 'America/New_York'))->getTimezone())->toBe('America/New_York');
});

it('returns a carbon instance', function () {
    $next = (new RecurrSchedule('FREQ=DAILY;DTSTART=20260501T090000'))->getNextOccurrence(now());

    expect($next)->toBeInstanceOf(Carbon::class);
});
