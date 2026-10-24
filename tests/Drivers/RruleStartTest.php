<?php

use Carbon\Carbon;
use DirectoryTree\Cadence\Drivers\RecurrSchedule;
use DirectoryTree\Cadence\Drivers\RruleSchedule;

dataset('rrule drivers', [RruleSchedule::class, RecurrSchedule::class]);

it('preserves the default start across repeated evaluations and serialization', function ($driverClass) {
    Carbon::setTestNow('2026-05-02 10:00:00');

    $driver = new $driverClass('FREQ=SECONDLY;COUNT=2');

    expect($driver->getNextOccurrence(now())?->toDateTimeString())->toBe('2026-05-02 10:00:01');

    Carbon::setTestNow('2026-05-02 10:00:05');

    $expression = $driver->toExpression();
    $restored = $driverClass::fromExpression($expression)->setTimezone($driver->getTimezone());

    expect($expression)->toBe('FREQ=SECONDLY;COUNT=2;DTSTART=20260502T100000')
        ->and($driver->getNextOccurrence(now()))->toBeNull()
        ->and($restored->getNextOccurrence(now()))->toBeNull();
})->with('rrule drivers');

it('uses the configured timezone for a default start', function ($driverClass, $date, $expected) {
    Carbon::setTestNow(Carbon::parse($date, 'UTC'));

    $driver = (new $driverClass('FREQ=DAILY;BYHOUR=9;BYMINUTE=0;BYSECOND=0'))
        ->setTimezone('America/New_York');

    expect($driver->getNextOccurrence(now())?->utc()->toDateTimeString())->toBe($expected);
})->with('rrule drivers')->with([
    'daylight saving time' => ['2026-05-02 12:00:00', '2026-05-02 13:00:00'],
    'standard time' => ['2026-12-02 12:00:00', '2026-12-02 14:00:00'],
]);

it('leaves an explicit start unchanged', function ($driverClass) {
    Carbon::setTestNow('2026-05-02 10:00:00');

    $expression = 'DTSTART=20260501T090000;FREQ=DAILY;COUNT=3';
    $driver = new $driverClass($expression, 'America/New_York');

    expect($driver->toExpression())->toBe($expression)
        ->and($driver->getNextOccurrence(now())?->utc()->toDateTimeString())->toBe('2026-05-02 13:00:00');
})->with('rrule drivers');

it('honours until when adding a default start', function ($driverClass) {
    Carbon::setTestNow('2026-05-02 10:00:00');

    $driver = new $driverClass('FREQ=DAILY;UNTIL=20260503T100000Z');

    expect($driver->getNextOccurrence(now())?->toDateTimeString())->toBe('2026-05-03 10:00:00')
        ->and($driver->getNextOccurrence(Carbon::parse('2026-05-03 10:00:00')))->toBeNull();
})->with('rrule drivers');

it('keeps the schedule timezone across daylight saving changes', function ($driverClass) {
    Carbon::setTestNow(Carbon::parse('2026-10-31 12:00:00', 'UTC'));

    $driver = new $driverClass('FREQ=DAILY;BYHOUR=9;BYMINUTE=0;BYSECOND=0', 'America/New_York');
    $expression = $driver->toExpression();

    Carbon::setTestNow(Carbon::parse('2026-11-01 12:00:00', 'UTC'));

    $restored = $driverClass::fromExpression($expression)->setTimezone($driver->getTimezone());

    expect($restored->getNextOccurrence(now())?->utc()->toDateTimeString())->toBe('2026-11-01 14:00:00');
})->with('rrule drivers');

it('preserves explicit RFC expressions', function () {
    $expression = "DTSTART;TZID=America/New_York:20260501T090000\nRRULE:FREQ=DAILY;COUNT=3";
    $driver = new RruleSchedule($expression);

    expect($driver->toExpression())->toBe($expression)
        ->and($driver->getNextOccurrence(Carbon::parse('2026-05-02 12:00:00', 'UTC'))?->utc()->toDateTimeString())
        ->toBe('2026-05-02 13:00:00');
});
