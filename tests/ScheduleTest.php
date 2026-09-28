<?php

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DirectoryTree\Cadence\Drivers\CronSchedule;
use DirectoryTree\Cadence\Drivers\ScheduleDriver;
use DirectoryTree\Cadence\Schedule;
use DirectoryTree\Cadence\Tests\Fixtures\SchedulableModel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->createScheduleTables();

    Schema::create('schedulable_models', function (Blueprint $table) {
        $table->id();
        $table->timestamps();
    });

    Carbon::setTestNow('2026-05-02 10:00:00');
});

it('can add a schedule to a model', function () {
    $model = SchedulableModel::create();
    $schedule = $model->addSchedule(new CronSchedule('0 12 * * *'));

    expect($schedule)->toBeInstanceOf(Schedule::class)
        ->and($schedule->type)->toBe('cron')
        ->and($schedule->expression)->toBe('0 12 * * *')
        ->and($schedule->schedulable_id)->toBe($model->id)
        ->and($schedule->schedulable_type)->toBe(SchedulableModel::class);
});

it('calculates next_run_at on creation', function () {
    $schedule = SchedulableModel::create()->addSchedule(new CronSchedule('0 12 * * *'));

    expect($schedule->next_run_at->format('Y-m-d H:i:s'))->toBe('2026-05-02 12:00:00');
});

it('stores timezone on creation', function () {
    $schedule = SchedulableModel::create()->addSchedule(
        new CronSchedule('0 9 * * *', 'America/New_York')
    );

    expect($schedule->timezone)->toBe('America/New_York');
});

it('stores null timezone when none provided', function () {
    $schedule = SchedulableModel::create()->addSchedule(new CronSchedule('0 12 * * *'));

    expect($schedule->timezone)->toBeNull();
});

it('resolves the driver from a stored schedule', function () {
    $schedule = SchedulableModel::create()->addSchedule(new CronSchedule('0 12 * * *'));

    expect($schedule->toDriver())
        ->toBeInstanceOf(CronSchedule::class)
        ->toExpression()->toBe('0 12 * * *');
});

it('resolves the driver with timezone from a stored schedule', function () {
    $schedule = SchedulableModel::create()->addSchedule(
        new CronSchedule('0 9 * * *', 'America/New_York')
    );

    expect($schedule->toDriver())
        ->toBeInstanceOf(CronSchedule::class)
        ->getTimezone()->toBe('America/New_York');
});

it('returns the schedulable relationship', function () {
    $model = SchedulableModel::create();
    $schedule = $model->addSchedule(new CronSchedule('0 12 * * *'));

    expect($schedule->schedulable)->toBeInstanceOf(SchedulableModel::class)
        ->and($schedule->schedulable->id)->toBe($model->id);
});

it('returns schedules from the model', function () {
    $model = SchedulableModel::create();
    $model->addSchedule(new CronSchedule('0 12 * * *'));
    $model->addSchedule(new CronSchedule('0 9 * * 1'));

    expect($model->schedules)->toHaveCount(2);
});

it('scopes to due schedules', function () {
    $model = SchedulableModel::create();
    $model->addSchedule(new CronSchedule('0 12 * * *'));  // due at noon
    $model->addSchedule(new CronSchedule('0 15 * * *'));  // due at 3pm

    // Before either is due
    Carbon::setTestNow('2026-05-02 11:00:00');

    expect(Schedule::due()->count())->toBe(0);

    // After noon, only the first is due
    Carbon::setTestNow('2026-05-02 12:01:00');

    expect(Schedule::due()->count())->toBe(1);

    // After 3pm, both are due
    Carbon::setTestNow('2026-05-02 15:01:00');

    expect(Schedule::due()->count())->toBe(2);
});

it('stores next_run_at as the correct moment for a timezone ahead of the app', function () {
    // 2026-05-02 10:00 UTC is 20:00 in Sydney (UTC+10), so the next 09:00 Sydney is 23:00 UTC.
    $schedule = SchedulableModel::create()->addSchedule(
        new CronSchedule('0 9 * * *', 'Australia/Sydney')
    );

    $schedule->refresh();

    expect($schedule->next_run_at->equalTo(Carbon::parse('2026-05-02 23:00:00', 'UTC')))->toBeTrue();

    Carbon::setTestNow('2026-05-02 22:59:00');

    expect(Schedule::due()->exists())->toBeFalse();

    Carbon::setTestNow('2026-05-02 23:00:30');

    expect(Schedule::due()->exists())->toBeTrue();
});

it('stores next_run_at as the correct moment for a timezone behind the app', function () {
    // 2026-05-02 10:00 UTC is 06:00 in New York (UTC-4), so the next 09:00 New York is 13:00 UTC.
    $schedule = SchedulableModel::create()->addSchedule(
        new CronSchedule('0 9 * * *', 'America/New_York')
    );

    $schedule->refresh();

    expect($schedule->next_run_at->equalTo(Carbon::parse('2026-05-02 13:00:00', 'UTC')))->toBeTrue();
    expect(Schedule::due()->exists())->toBeFalse();

    Carbon::setTestNow('2026-05-02 12:59:00');

    expect(Schedule::due()->exists())->toBeFalse();

    Carbon::setTestNow('2026-05-02 13:00:30');

    expect(Schedule::due()->exists())->toBeTrue();
});

it('scopes to due schedules using a date in another timezone', function () {
    // Next run is 2026-05-02 12:00 UTC, which is 22:00 in Sydney (UTC+10).
    SchedulableModel::create()->addSchedule(new CronSchedule('0 12 * * *'));

    $before = Carbon::parse('2026-05-02 21:59:00', 'Australia/Sydney');
    $after = Carbon::parse('2026-05-02 22:00:30', 'Australia/Sydney');

    expect(Schedule::due($before)->exists())->toBeFalse();
    expect(Schedule::due($after)->exists())->toBeTrue();
    expect($after->getTimezone()->getName())->toBe('Australia/Sydney');
});

it('excludes exhausted schedules from due scope', function () {
    $model = SchedulableModel::create();

    $model->schedules()->create([
        'type' => 'cron',
        'expression' => '0 12 * * *',
        'next_run_at' => null,
    ]);

    expect(Schedule::due()->count())->toBe(0);
});

it('throws when resolving an unregistered driver type', function () {
    $model = SchedulableModel::create();

    $schedule = $model->schedules()->create([
        'type' => 'unknown',
        'expression' => 'foo',
        'next_run_at' => now(),
    ]);

    $schedule->toDriver();
})->throws(InvalidArgumentException::class, 'Unknown schedule driver type: unknown');

it('can disable a schedule', function () {
    $schedule = SchedulableModel::create()->addSchedule(new CronSchedule('0 12 * * *'));

    expect($schedule->isEnabled())->toBeTrue();
    expect($schedule->isDisabled())->toBeFalse();

    $schedule->disable();

    $schedule->refresh();

    expect($schedule->isEnabled())->toBeFalse();
    expect($schedule->isDisabled())->toBeTrue();
    expect($schedule->disabled_at->format('Y-m-d H:i:s'))->toBe('2026-05-02 10:00:00');
    expect($schedule->next_run_at)->toBeNull();
});

it('can enable a disabled schedule', function () {
    Carbon::setTestNow('2026-05-02 10:00:00');

    $schedule = SchedulableModel::create()->addSchedule(new CronSchedule('0 12 * * *'));

    // Record last_run_at before disabling
    Carbon::setTestNow('2026-05-02 12:01:00');
    $schedule->update(['last_run_at' => now()]);

    Carbon::setTestNow('2026-05-02 13:00:00');

    $schedule->disable();

    $schedule->refresh();

    expect($schedule->isDisabled())->toBeTrue();
    expect($schedule->next_run_at)->toBeNull();

    // Enable should recompute next_run_at from now, leave last_run_at
    $schedule->enable();

    $schedule->refresh();

    expect($schedule->isEnabled())->toBeTrue();
    expect($schedule->disabled_at)->toBeNull();
    expect($schedule->next_run_at->format('Y-m-d H:i:s'))->toBe('2026-05-03 12:00:00');
    expect($schedule->last_run_at->format('Y-m-d H:i:s'))->toBe('2026-05-02 12:01:00');
});

it('recomputes next_run_at as the correct moment when enabling a schedule with a timezone', function () {
    $schedule = SchedulableModel::create()->addSchedule(
        new CronSchedule('0 9 * * *', 'Australia/Sydney')
    );

    $schedule->disable();

    // 2026-05-03 00:00 UTC is 10:00 in Sydney, so the next 09:00 Sydney is 2026-05-03 23:00 UTC.
    Carbon::setTestNow('2026-05-03 00:00:00');

    $schedule->enable();

    $schedule->refresh();

    expect($schedule->next_run_at->equalTo(Carbon::parse('2026-05-03 23:00:00', 'UTC')))->toBeTrue();
});

it('can advance a schedule to its next occurrence', function () {
    $schedule = SchedulableModel::create()->addSchedule(new CronSchedule('0 12 * * *'));

    $schedule->advance(Carbon::parse('2026-05-02 12:01:00'));

    $schedule->refresh();

    expect($schedule->last_run_at->format('Y-m-d H:i:s'))->toBe('2026-05-02 12:01:00');
    expect($schedule->next_run_at->format('Y-m-d H:i:s'))->toBe('2026-05-03 12:00:00');
});

it('advances next_run_at to the correct moment for a schedule with a timezone', function () {
    $schedule = SchedulableModel::create()->addSchedule(
        new CronSchedule('0 9 * * *', 'Australia/Sydney')
    );

    $schedule->advance(Carbon::parse('2026-05-02 23:00:30', 'UTC'));

    $schedule->refresh();

    expect($schedule->last_run_at->equalTo(Carbon::parse('2026-05-02 23:00:30', 'UTC')))->toBeTrue();
    expect($schedule->next_run_at->equalTo(Carbon::parse('2026-05-03 23:00:00', 'UTC')))->toBeTrue();
});

it('stores dates given in another timezone as the same moment without mutating them', function () {
    $schedule = SchedulableModel::create()->addSchedule(new CronSchedule('0 12 * * *'));

    $date = Carbon::parse('2026-05-03 09:00:00', 'Australia/Sydney');

    $schedule->update(['next_run_at' => $date]);

    $schedule->refresh();

    expect($schedule->next_run_at->equalTo(Carbon::parse('2026-05-02 23:00:00', 'UTC')))->toBeTrue();
    expect($date->getTimezone()->getName())->toBe('Australia/Sydney');
    expect($date->format('Y-m-d H:i:s'))->toBe('2026-05-03 09:00:00');
});

it('scopes to enabled schedules', function () {
    $model = SchedulableModel::create();
    $enabled = $model->addSchedule(new CronSchedule('0 12 * * *'));
    $disabled = $model->addSchedule(new CronSchedule('0 15 * * *'));

    $disabled->disable();

    expect(Schedule::enabled()->count())->toBe(1);
    expect(Schedule::disabled()->count())->toBe(1);
    expect(Schedule::enabled()->first()->id)->toBe($enabled->id);
    expect(Schedule::disabled()->first()->id)->toBe($disabled->id);
});

it('throws when adding a schedule with an unregistered driver', function () {
    $driver = new class implements ScheduleDriver
    {
        public static function fromExpression(string $expression): static
        {
            return new self;
        }

        public function setTimezone(string $timezone): static
        {
            return $this;
        }

        public function getTimezone(): ?string
        {
            return null;
        }

        public function toExpression(): string
        {
            return '';
        }

        public function getNextOccurrence(CarbonInterface $after): ?CarbonInterface
        {
            return null;
        }
    };

    SchedulableModel::create()->addSchedule($driver);
})->throws(InvalidArgumentException::class, 'Unregistered schedule driver');
