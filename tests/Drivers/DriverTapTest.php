<?php

use Carbon\Carbon;
use Cron\CronExpression;
use DirectoryTree\Cadence\Drivers\CronSchedule;
use DirectoryTree\Cadence\Drivers\RecurrSchedule;
use DirectoryTree\Cadence\Drivers\RruleSchedule;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use RRule\RRule;

afterEach(function () {
    CronSchedule::tap(null);
    RruleSchedule::tap(null);
    RecurrSchedule::tap(null);
});

it('keeps typed callbacks registered for all drivers', function () {
    Carbon::setTestNow('2026-05-02 08:00:00');
    $called = [];

    CronSchedule::tap(function (CronExpression $cron, CronSchedule $driver) use (&$called) {
        $called[] = $driver::class;
    });

    RruleSchedule::tap(function (RRule $rule) use (&$called) {
        $called[] = RruleSchedule::class;
    });

    RecurrSchedule::tap(function (Rule $rule, ArrayTransformer $transformer, RecurrSchedule $driver) use (&$called) {
        $called[] = $driver::class;
    });

    (new CronSchedule('0 9 * * *'))->getNextOccurrence(now());
    (new RruleSchedule('FREQ=DAILY;DTSTART=20260501T090000'))->getNextOccurrence(now());
    (new RecurrSchedule('FREQ=DAILY;DTSTART=20260501T090000'))->getNextOccurrence(now());

    expect($called)->toBe([CronSchedule::class, RruleSchedule::class, RecurrSchedule::class]);
});

it('does not invoke a callback registered for another driver', function () {
    Carbon::setTestNow('2026-05-02 08:00:00');
    $called = false;

    RecurrSchedule::tap(function (Rule $rule, ArrayTransformer $transformer) use (&$called) {
        $called = true;
    });

    (new CronSchedule('0 9 * * *'))->getNextOccurrence(now());
    (new RruleSchedule('FREQ=DAILY;DTSTART=20260501T090000'))->getNextOccurrence(now());

    expect($called)->toBeFalse();
});

it('clears only the callback for the requested driver', function ($cleared) {
    Carbon::setTestNow('2026-05-02 08:00:00');
    $called = [];
    $drivers = [
        new CronSchedule('0 9 * * *'),
        new RruleSchedule('FREQ=DAILY;DTSTART=20260501T090000'),
        new RecurrSchedule('FREQ=DAILY;DTSTART=20260501T090000'),
    ];

    foreach ($drivers as $driver) {
        $driver::tap(function () use (&$called, $driver) {
            $called[] = $driver::class;
        });
    }

    $cleared::tap(null);

    foreach ($drivers as $driver) {
        $driver->getNextOccurrence(now());
    }

    expect($called)->toBe(array_values(array_diff([
        CronSchedule::class, RruleSchedule::class, RecurrSchedule::class,
    ], [$cleared])));
})->with([CronSchedule::class, RruleSchedule::class, RecurrSchedule::class]);

it('keeps subclass callbacks separate from their parent driver', function () {
    Carbon::setTestNow('2026-05-02 08:00:00');
    $called = [];
    $custom = new class('0 9 * * *') extends CronSchedule {};

    CronSchedule::tap(function (CronExpression $cron, CronSchedule $driver) use (&$called) {
        $called[] = $driver::class;
    });

    $custom::tap(function (CronExpression $cron, CronSchedule $driver) use (&$called) {
        $called[] = $driver::class;
    });

    try {
        (new CronSchedule('0 9 * * *'))->getNextOccurrence(now());
        $custom->getNextOccurrence(now());
        $custom::tap(null);
        (new CronSchedule('0 9 * * *'))->getNextOccurrence(now());

        expect($called)->toBe([CronSchedule::class, $custom::class, CronSchedule::class]);
    } finally {
        $custom::tap(null);
    }
});
