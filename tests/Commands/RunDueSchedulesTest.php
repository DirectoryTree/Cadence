<?php

use Carbon\Carbon;
use DirectoryTree\Cadence\Drivers\CronSchedule;
use DirectoryTree\Cadence\Drivers\RruleSchedule;
use DirectoryTree\Cadence\Events\ScheduleTriggered;
use DirectoryTree\Cadence\Tests\Fixtures\SchedulableModel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('schedules', function (Blueprint $table) {
        $table->id();
        $table->morphs('schedulable');
        $table->string('type');
        $table->text('expression');
        $table->string('timezone')->nullable();
        $table->timestamp('next_run_at')->nullable()->index();
        $table->timestamp('last_run_at')->nullable();
        $table->timestamps();
    });

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
