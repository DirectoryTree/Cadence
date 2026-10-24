<?php

use Carbon\Carbon;
use DirectoryTree\Cadence\Drivers\RecurrSchedule;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\ArrayTransformerConfig;

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

it('finds the next occurrence beyond the historical transformation limit', function ($expression, $after, $expected) {
    $next = (new RecurrSchedule($expression))->getNextOccurrence(Carbon::parse($after, 'UTC'));

    expect($next?->toDateTimeString())->toBe($expected);
})->with([
    'daily' => ['FREQ=DAILY;DTSTART=20200101T090000', '2026-05-02 10:00:00', '2026-05-03 09:00:00'],
    'hourly' => ['FREQ=HOURLY;DTSTART=20260501T090000', '2026-06-02 10:00:00', '2026-06-02 11:00:00'],
    'count' => ['FREQ=DAILY;COUNT=1000;DTSTART=20240101T090000', '2026-05-02 10:00:00', '2026-05-03 09:00:00'],
    'until' => ['FREQ=DAILY;UNTIL=20260503T090000Z;DTSTART=20200101T090000', '2026-05-02 10:00:00', '2026-05-03 09:00:00'],
    'excluded next occurrence' => ['FREQ=DAILY;EXDATE=20260503T090000;DTSTART=20200101T090000', '2026-05-02 10:00:00', '2026-05-04 09:00:00'],
    'set position' => ['FREQ=DAILY;BYHOUR=9,10;BYSETPOS=1;COUNT=1000;DTSTART=20240101T090000', '2026-05-02 10:00:00', '2026-05-03 09:00:00'],
]);

it('preserves the last occurrence and exhaustion of a counted rule', function ($count) {
    $driver = new RecurrSchedule("FREQ=DAILY;COUNT={$count};DTSTART=20240101T090000");
    $last = Carbon::parse('2024-01-01 09:00:00', 'UTC')->addDays($count - 1);

    expect($driver->getNextOccurrence($last->copy()->subSecond())?->equalTo($last))->toBeTrue()
        ->and($driver->getNextOccurrence($last))->toBeNull();
})->with([1, 3, 1000]);

it('does not extend a rule beyond its until date', function () {
    $driver = new RecurrSchedule('FREQ=DAILY;UNTIL=20260503T090000Z;DTSTART=20200101T090000');
    $last = Carbon::parse('2026-05-03 09:00:00', 'UTC');

    expect($driver->getNextOccurrence($last->copy()->subSecond())?->equalTo($last))->toBeTrue()
        ->and($driver->getNextOccurrence($last))->toBeNull();
});

it('preserves count when the transformation limit is customized', function () {
    RecurrSchedule::tap(function (Rule $rule, ArrayTransformer $transformer) {
        $transformer->setConfig((new ArrayTransformerConfig)->setVirtualLimit(1));
    });

    try {
        $driver = new RecurrSchedule('FREQ=DAILY;COUNT=3;DTSTART=20260501T090000');
        $last = Carbon::parse('2026-05-03 09:00:00', 'UTC');

        expect($driver->getNextOccurrence($last->copy()->subSecond())?->equalTo($last))->toBeTrue()
            ->and($driver->getNextOccurrence($last))->toBeNull();
    } finally {
        RecurrSchedule::tap(null);
    }
});
