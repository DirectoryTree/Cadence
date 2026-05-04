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
