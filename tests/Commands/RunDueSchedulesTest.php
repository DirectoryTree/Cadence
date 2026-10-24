<?php

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DirectoryTree\Cadence\Drivers\CronSchedule;
use DirectoryTree\Cadence\Drivers\RecurrSchedule;
use DirectoryTree\Cadence\Drivers\RruleSchedule;
use DirectoryTree\Cadence\Events\ScheduleTriggered;
use DirectoryTree\Cadence\Schedule;
use DirectoryTree\Cadence\Tests\Fixtures\SchedulableModel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\artisan;

beforeEach(function () {
    $this->createScheduleTables();

    Schema::create('schedulable_models', function (Blueprint $table) {
        $table->id();
        $table->timestamps();
    });
});

it('dispatches event for due schedules', function () {
    Event::fake();
    Carbon::setTestNow('2026-05-02 12:00:00');

    $model = SchedulableModel::create();
    $model->addSchedule(new CronSchedule('0 12 * * *'));

    // Advance time so the schedule is due
    Carbon::setTestNow('2026-05-03 12:01:00');

    $this->artisan('schedules:run')->assertSuccessful();

    Event::assertDispatched(ScheduleTriggered::class, function ($event) use ($model) {
        return $event->schedule->schedulable_id === $model->id
            && $event->schedule->schedulable_type === SchedulableModel::class;
    });
});

it('captures the scheduled and dispatch times before advancing the schedule', function ($dispatchedAt) {
    Event::fake();
    Carbon::setTestNow('2026-05-02 08:00:00');

    $schedule = SchedulableModel::create()->addSchedule(new CronSchedule('0 9 * * *'));

    Carbon::setTestNow($dispatchedAt);

    artisan('schedules:run')->assertSuccessful();

    $schedule->refresh();

    Event::assertDispatched(ScheduleTriggered::class, function ($event) use ($schedule, $dispatchedAt) {
        expect($event->scheduledAt)->toBeInstanceOf(CarbonImmutable::class)
            ->and($event->dispatchedAt)->toBeInstanceOf(CarbonImmutable::class)
            ->and($event->scheduledAt->toDateTimeString())->toBe('2026-05-02 09:00:00')
            ->and($event->dispatchedAt->toDateTimeString())->toBe($dispatchedAt)
            ->and($schedule->last_run_at->equalTo($event->dispatchedAt))->toBeTrue()
            ->and($schedule->next_run_at->greaterThan($event->dispatchedAt))->toBeTrue();

        return true;
    });
})->with([
    'on time' => '2026-05-02 09:00:00',
    'late' => '2026-05-03 00:30:00',
]);

it('captures the dispatch time for each occurrence as it is processed', function () {
    Carbon::setTestNow('2026-05-02 08:00:00');

    $model = SchedulableModel::create();
    $first = $model->addSchedule(new CronSchedule('0 9 * * *'));
    $second = $model->addSchedule(new CronSchedule('0 9 * * *'));

    $dispatchTimes = [];

    Event::listen(ScheduleTriggered::class, function ($event) use (&$dispatchTimes) {
        $dispatchTimes[] = $event->dispatchedAt->toDateTimeString();

        Carbon::setTestNow(now()->addMinute());
    });

    Carbon::setTestNow('2026-05-02 09:00:00');

    artisan('schedules:run')->assertSuccessful();

    expect($dispatchTimes)->toBe(['2026-05-02 09:00:00', '2026-05-02 09:01:00'])
        ->and($first->refresh()->last_run_at->toDateTimeString())->toBe('2026-05-02 09:00:00')
        ->and($second->refresh()->last_run_at->toDateTimeString())->toBe('2026-05-02 09:01:00');
});

it('preserves occurrence timestamps when a serialized event reloads an advanced schedule', function () {
    Carbon::setTestNow('2026-05-02 08:00:00');

    $schedule = SchedulableModel::create()->addSchedule(new CronSchedule('0 9 * * *'));

    $serializedEvent = null;

    Event::listen(ScheduleTriggered::class, function ($event) use (&$serializedEvent) {
        $serializedEvent = serialize($event);
    });

    Carbon::setTestNow('2026-05-03 00:30:00');

    artisan('schedules:run')->assertSuccessful();

    Carbon::setTestNow('2026-05-03 01:00:00');

    $event = unserialize($serializedEvent);

    expect($event->schedule->id)->toBe($schedule->id)
        ->and($event->schedule->next_run_at->toDateTimeString())->toBe('2026-05-03 09:00:00')
        ->and($event->scheduledAt)->toBeInstanceOf(CarbonImmutable::class)
        ->and($event->scheduledAt->toDateTimeString())->toBe('2026-05-02 09:00:00')
        ->and($event->dispatchedAt)->toBeInstanceOf(CarbonImmutable::class)
        ->and($event->dispatchedAt->toDateTimeString())->toBe('2026-05-03 00:30:00');
});

it('advances a late schedule when a listener declines the work', function () {
    Carbon::setTestNow('2026-05-02 08:00:00');

    $schedule = SchedulableModel::create()->addSchedule(new CronSchedule('0 9 * * *'));

    $handled = false;

    Event::listen(ScheduleTriggered::class, function ($event) use (&$handled) {
        if ($event->dispatchedAt->greaterThan($event->scheduledAt->addMinutes(5))) {
            return;
        }

        $handled = true;
    });

    Carbon::setTestNow('2026-05-03 00:30:00');

    artisan('schedules:run')->assertSuccessful();

    $schedule->refresh();

    expect($handled)->toBeFalse()
        ->and($schedule->last_run_at->toDateTimeString())->toBe('2026-05-03 00:30:00')
        ->and($schedule->next_run_at->toDateTimeString())->toBe('2026-05-03 09:00:00');
});

it('copies mutable timestamps when constructing the event', function () {
    Carbon::setTestNow('2026-05-02 08:00:00');

    $schedule = SchedulableModel::create()->addSchedule(new CronSchedule('0 9 * * *'));
    $dispatchedAt = Carbon::parse('2026-05-02 09:01:00');

    $event = new ScheduleTriggered($schedule, $dispatchedAt);

    $schedule->next_run_at = $schedule->next_run_at->addDay();
    $dispatchedAt->addHour();

    expect($event->scheduledAt->toDateTimeString())->toBe('2026-05-02 09:00:00')
        ->and($event->dispatchedAt->toDateTimeString())->toBe('2026-05-02 09:01:00');
});

it('defaults the event dispatch time to now', function () {
    Carbon::setTestNow('2026-05-02 08:00:00');

    $schedule = SchedulableModel::create()->addSchedule(new CronSchedule('0 9 * * *'));

    Carbon::setTestNow('2026-05-02 09:01:00');

    $event = new ScheduleTriggered($schedule);

    expect($event->scheduledAt->toDateTimeString())->toBe('2026-05-02 09:00:00')
        ->and($event->dispatchedAt->toDateTimeString())->toBe('2026-05-02 09:01:00');
});

it('does not dispatch event for non-due schedules', function () {
    Event::fake();
    Carbon::setTestNow('2026-05-02 10:00:00');

    $model = SchedulableModel::create();
    $model->addSchedule(new CronSchedule('0 12 * * *'));

    // Still before noon — not due yet
    Carbon::setTestNow('2026-05-02 11:00:00');

    $this->artisan('schedules:run')->assertSuccessful();

    Event::assertNotDispatched(ScheduleTriggered::class);
});

it('advances next_run_at after dispatching', function () {
    Event::fake();
    Carbon::setTestNow('2026-05-02 10:00:00');

    $model = SchedulableModel::create();
    $schedule = $model->addSchedule(new CronSchedule('0 12 * * *'));

    // next_run_at should be today at noon
    expect($schedule->next_run_at->format('Y-m-d H:i:s'))->toBe('2026-05-02 12:00:00');

    // Advance past noon so it's due
    Carbon::setTestNow('2026-05-02 12:01:00');

    $this->artisan('schedules:run')->assertSuccessful();

    $schedule->refresh();

    // Should advance to tomorrow at noon
    expect($schedule->next_run_at->format('Y-m-d H:i:s'))->toBe('2026-05-03 12:00:00');
});

it('sets last_run_at after dispatching', function () {
    Event::fake();
    Carbon::setTestNow('2026-05-02 10:00:00');

    $model = SchedulableModel::create();
    $schedule = $model->addSchedule(new CronSchedule('0 12 * * *'));

    expect($schedule->last_run_at)->toBeNull();

    Carbon::setTestNow('2026-05-02 12:01:00');

    $this->artisan('schedules:run')->assertSuccessful();

    $schedule->refresh();

    expect($schedule->last_run_at->format('Y-m-d H:i:s'))->toBe('2026-05-02 12:01:00');
});

it('skips disabled schedules', function () {
    Event::fake();
    Carbon::setTestNow('2026-05-02 12:00:00');

    $model = SchedulableModel::create();
    $schedule = $model->addSchedule(new CronSchedule('0 12 * * *'));
    $schedule->disable();

    // Advance time so the schedule would be due if not disabled
    Carbon::setTestNow('2026-05-03 12:01:00');

    $this->artisan('schedules:run')->assertSuccessful();

    Event::assertNotDispatched(ScheduleTriggered::class);
});

it('runs due schedules when disabled at has not been migrated', function () {
    Event::fake();
    Carbon::setTestNow('2026-05-02 12:00:00');

    Schema::table('schedules', function (Blueprint $table) {
        $table->dropColumn('disabled_at');
    });

    $model = SchedulableModel::create();
    $model->addSchedule(new CronSchedule('0 12 * * *'));

    // Advance time so the schedule is due
    Carbon::setTestNow('2026-05-03 12:01:00');

    $this->artisan('schedules:run')->assertSuccessful();

    Event::assertDispatched(ScheduleTriggered::class);
});

it('does not pick up schedules with null next_run_at', function () {
    Event::fake();
    Carbon::setTestNow('2026-05-02 12:00:00');

    $model = SchedulableModel::create();

    // Manually create a schedule with null next_run_at (exhausted)
    $model->schedules()->create([
        'type' => 'cron',
        'expression' => '0 12 * * *',
        'next_run_at' => null,
    ]);

    $this->artisan('schedules:run')->assertSuccessful();

    Event::assertNotDispatched(ScheduleTriggered::class);
});

it('does not re-dispatch on subsequent runs after firing', function () {
    Event::fake();
    Carbon::setTestNow('2026-05-02 10:00:00');

    $model = SchedulableModel::create();
    $model->addSchedule(new CronSchedule('0 12 * * *'));

    // Advance past noon — schedule is due
    Carbon::setTestNow('2026-05-02 12:01:00');

    $this->artisan('schedules:run')->assertSuccessful();

    Event::assertDispatchedTimes(ScheduleTriggered::class, 1);

    // Run again one minute later — should NOT fire again
    Carbon::setTestNow('2026-05-02 12:02:00');

    $this->artisan('schedules:run')->assertSuccessful();

    Event::assertDispatchedTimes(ScheduleTriggered::class, 1);

    // Run again much later, still before next occurrence (tomorrow noon)
    Carbon::setTestNow('2026-05-02 23:59:00');

    $this->artisan('schedules:run')->assertSuccessful();

    Event::assertDispatchedTimes(ScheduleTriggered::class, 1);
});

it('does not re-dispatch rrule schedule on same day after firing', function () {
    Event::fake();

    // Use an explicit DTSTART so the time is deterministic.
    // 2026-05-04 is a Monday.
    Carbon::setTestNow('2026-05-03 12:00:00');

    $model = SchedulableModel::create();
    $schedule = $model->addSchedule(
        new RruleSchedule('DTSTART=20260504T090000;FREQ=DAILY;BYDAY=MO,TU,WE,TH,FR')
    );

    // next_run_at should be Monday at 09:00
    expect($schedule->next_run_at->format('Y-m-d H:i:s'))->toBe('2026-05-04 09:00:00');

    // Fire on Monday after 9am
    Carbon::setTestNow('2026-05-04 09:01:00');

    $this->artisan('schedules:run')->assertSuccessful();

    Event::assertDispatchedTimes(ScheduleTriggered::class, 1);

    $schedule->refresh();

    // next_run_at should now be Tuesday at 09:00
    expect($schedule->next_run_at->format('Y-m-d H:i:s'))->toBe('2026-05-05 09:00:00');

    // Run again later on Monday — should NOT fire
    Carbon::setTestNow('2026-05-04 12:00:00');

    $this->artisan('schedules:run')->assertSuccessful();

    Event::assertDispatchedTimes(ScheduleTriggered::class, 1);

    // Run again at end of Monday — still should NOT fire
    Carbon::setTestNow('2026-05-04 23:59:00');

    $this->artisan('schedules:run')->assertSuccessful();

    Event::assertDispatchedTimes(ScheduleTriggered::class, 1);
});

it('dispatches events for multiple due schedules', function () {
    Event::fake();
    Carbon::setTestNow('2026-05-02 10:00:00');

    $model = SchedulableModel::create();
    $model->addSchedule(new CronSchedule('0 12 * * *'));
    $model->addSchedule(new CronSchedule('30 11 * * *'));

    // Both should be due after noon
    Carbon::setTestNow('2026-05-02 12:01:00');

    $this->artisan('schedules:run')->assertSuccessful();

    Event::assertDispatchedTimes(ScheduleTriggered::class, 2);
});

it('keeps a long-running Recurr schedule active after dispatching', function () {
    Event::fake();
    Carbon::setTestNow('2020-01-01 08:00:00');

    $schedule = SchedulableModel::create()->addSchedule(
        new RecurrSchedule('FREQ=DAILY;DTSTART=20200101T090000')
    );

    Carbon::setTestNow('2026-05-02 10:00:00');

    artisan('schedules:run')->assertSuccessful();

    expect($schedule->refresh()->next_run_at?->toDateTimeString())->toBe('2026-05-03 09:00:00');

    Carbon::setTestNow('2026-05-03 09:01:00');

    artisan('schedules:run')->assertSuccessful();

    Event::assertDispatchedTimes(ScheduleTriggered::class, 2);

    expect($schedule->refresh()->next_run_at?->toDateTimeString())->toBe('2026-05-04 09:00:00');
});

it('continues running schedules when a listener throws and retries the failed occurrence', function ($exceptionClass) {
    Exceptions::fake();
    Carbon::setTestNow('2026-05-02 08:00:00');

    $model = SchedulableModel::create();
    $failed = $model->addSchedule(new CronSchedule('0 9 * * *'));
    $second = $model->addSchedule(new CronSchedule('0 9 * * *'));
    $third = $model->addSchedule(new CronSchedule('0 9 * * *'));

    $exception = new $exceptionClass('Schedule listener failed.');
    $handled = [];

    Event::listen(ScheduleTriggered::class, function ($event) use ($failed, $exception, &$handled) {
        if ($event->schedule->is($failed)) {
            throw $exception;
        }

        $handled[] = $event->schedule->id;
    });

    Carbon::setTestNow('2026-05-02 09:01:00');

    artisan('schedules:run')->assertSuccessful();

    Exceptions::assertReported($exceptionClass);
    Exceptions::assertReportedCount(1);

    expect($handled)->toBe([$second->id, $third->id])
        ->and($failed->refresh()->next_run_at->toDateTimeString())->toBe('2026-05-02 09:00:00')
        ->and($failed->last_run_at)->toBeNull()
        ->and($second->refresh()->next_run_at->toDateTimeString())->toBe('2026-05-03 09:00:00')
        ->and($third->refresh()->next_run_at->toDateTimeString())->toBe('2026-05-03 09:00:00');

    Event::forget(ScheduleTriggered::class);
    Event::listen(ScheduleTriggered::class, function ($event) use (&$handled) {
        $handled[] = $event->schedule->id;
    });

    artisan('schedules:run')->assertSuccessful();

    expect($handled)->toBe([$second->id, $third->id, $failed->id])
        ->and($failed->refresh()->last_run_at->toDateTimeString())->toBe('2026-05-02 09:01:00')
        ->and($failed->next_run_at->toDateTimeString())->toBe('2026-05-03 09:00:00');
})->with([RuntimeException::class, Error::class]);

it('continues running schedules when a driver cannot calculate the next occurrence', function () {
    Exceptions::fake();
    Event::fake();
    Carbon::setTestNow('2026-05-02 08:00:00');

    $model = SchedulableModel::create();
    $failed = $model->addSchedule(new CronSchedule('0 9 * * *'));
    $next = $model->addSchedule(new CronSchedule('0 9 * * *'));

    $failed->update(['expression' => 'invalid']);

    Carbon::setTestNow('2026-05-02 09:01:00');

    artisan('schedules:run')->assertSuccessful();

    Exceptions::assertReported(InvalidArgumentException::class);
    Exceptions::assertReportedCount(1);
    Event::assertDispatchedTimes(ScheduleTriggered::class, 2);

    expect($failed->refresh()->next_run_at->toDateTimeString())->toBe('2026-05-02 09:00:00')
        ->and($failed->last_run_at)->toBeNull()
        ->and($next->refresh()->next_run_at->toDateTimeString())->toBe('2026-05-03 09:00:00')
        ->and($next->last_run_at->toDateTimeString())->toBe('2026-05-02 09:01:00');
});

it('continues running schedules when saving an occurrence fails', function () {
    Exceptions::fake();
    Carbon::setTestNow('2026-05-02 08:00:00');

    $model = SchedulableModel::create();
    $failed = $model->addSchedule(new CronSchedule('0 9 * * *'));
    $next = $model->addSchedule(new CronSchedule('0 9 * * *'));

    $exception = new RuntimeException('Schedule update failed.');
    $triggered = [];

    Event::listen(ScheduleTriggered::class, function ($event) use (&$triggered) {
        $triggered[] = $event->schedule->id;
    });

    Schedule::updating(function ($schedule) use ($failed, $exception) {
        if ($schedule->is($failed)) {
            throw $exception;
        }
    });

    Carbon::setTestNow('2026-05-02 09:01:00');

    artisan('schedules:run')->assertSuccessful();

    Exceptions::assertReported(fn (RuntimeException $reported) => $reported === $exception);
    Exceptions::assertReportedCount(1);
    expect($triggered)->toBe([$failed->id, $next->id])
        ->and($failed->refresh()->next_run_at->toDateTimeString())->toBe('2026-05-02 09:00:00')
        ->and($failed->last_run_at)->toBeNull()
        ->and($next->refresh()->next_run_at->toDateTimeString())->toBe('2026-05-03 09:00:00')
        ->and($next->last_run_at->toDateTimeString())->toBe('2026-05-02 09:01:00');
});
