<p align="center">
<img src="https://github.com/DirectoryTree/Cadence/blob/master/art/logo.svg" width="250">
</p>

<p align="center">
Model-based scheduling for Laravel.
</p>

<p align="center">
<a href="https://github.com/directorytree/cadence/actions" target="_blank"><img src="https://img.shields.io/github/actions/workflow/status/directorytree/cadence/run-tests.yml?branch=master&style=flat-square"/></a>
<a href="https://packagist.org/packages/directorytree/cadence" target="_blank"><img src="https://img.shields.io/packagist/v/directorytree/cadence.svg?style=flat-square"/></a>
<a href="https://packagist.org/packages/directorytree/cadence" target="_blank"><img src="https://img.shields.io/packagist/dt/directorytree/cadence.svg?style=flat-square"/></a>
<a href="https://packagist.org/packages/directorytree/cadence" target="_blank"><img src="https://img.shields.io/packagist/l/directorytree/cadence.svg?style=flat-square"/></a>
</p>

---

Cadence provides a driver-based scheduling system for your Eloquent models using cron expressions or RRULE recurrence patterns. Attach one or many schedules to any model, and Cadence will track and dispatch events when they're due.

## Index

- [Requirements](#requirements)
- [Installation](#installation)
- [Setup](#setup)
- [Usage](#usage)
  - [Adding Schedules](#adding-schedules)
  - [Timezones](#timezones)
  - [Running Due Schedules](#running-due-schedules)
  - [Listening for Triggered Schedules](#listening-for-triggered-schedules)
- [Drivers](#drivers)
  - [Cron](#cron)
  - [RRULE (php-rrule)](#rrule-php-rrule)
  - [RRULE (Recurr)](#rrule-recurr)
  - [Custom Drivers](#custom-drivers)
- [Customizing Drivers](#customizing-drivers)

## Requirements

- PHP >= 8.2
- Laravel >= 11.0

## Installation

You can install the package via composer:

```bash
composer require directorytree/cadence
```

Then, install at least one schedule driver:

```bash
# Cron (recommended for simple schedules)
composer require dragonmantank/cron-expression

# RRULE via php-rrule
composer require rlanvin/php-rrule

# RRULE via Recurr
composer require simshaun/recurr
```

Publish and run the migrations:

```bash
php artisan vendor:publish --provider="DirectoryTree\Cadence\CadenceServiceProvider"
php artisan migrate
```

This creates a `schedules` table with the following columns:

- `schedulable_type` / `schedulable_id` — polymorphic relation to your model
- `type` — the driver type (e.g. `cron`, `rrule`, `recurr`)
- `expression` — the schedule expression
- `timezone` — optional timezone for the schedule
- `next_run_at` — precomputed next occurrence for efficient querying
- `last_run_at` — timestamp of the last run

## Setup

Implement the `Schedulable` interface and use the `HasSchedules` trait on any model you want to schedule:

```php
// app/Models/Report.php

namespace App\Models;

use DirectoryTree\Cadence\HasSchedules;
use DirectoryTree\Cadence\Schedulable;
use Illuminate\Database\Eloquent\Model;

class Report extends Model implements Schedulable
{
    use HasSchedules;
}
```

## Usage

### Adding Schedules

Create a driver instance and add it to your model:

```php
use DirectoryTree\Cadence\Drivers\CronSchedule;

$report = Report::find(1);

// Every day at noon
$report->addSchedule(new CronSchedule('0 12 * * *'));

// Every Monday at 9am
$report->addSchedule(new CronSchedule('0 9 * * 1'));
```

With RRULE expressions:

```php
use DirectoryTree\Cadence\Drivers\RruleSchedule;

// Every weekday
$report->addSchedule(new RruleSchedule('FREQ=DAILY;BYDAY=MO,TU,WE,TH,FR'));

// Monthly on the 15th, starting from a specific date
$report->addSchedule(new RruleSchedule('DTSTART=20260101T000000;FREQ=MONTHLY;BYMONTHDAY=15'));
```

### Timezones

Schedules can be timezone-aware. Pass the timezone as the second argument:

```php
// Every day at 9am Eastern
$report->addSchedule(
    new CronSchedule('0 9 * * *', 'America/New_York')
);
```

Or set it via the `setTimezone()` method:

```php
$schedule = new CronSchedule('0 9 * * *');

$schedule->setTimezone('America/New_York');

$report->addSchedule($schedule);
```

### Running Due Schedules

Register the `schedules:run` command in your application's scheduler to run every minute:

```php
// routes/console.php

use Illuminate\Support\Facades\Schedule;

Schedule::command('schedules:run')
    ->withoutOverlapping()
    ->everyMinute();
```

This command queries all schedules where `next_run_at <= now()`, dispatches a `ScheduleTriggered` event for each, and advances `next_run_at` to the next occurrence.

### Listening for Triggered Schedules

Listen for the `ScheduleTriggered` event to perform work when a schedule fires:

```php
// app/Listeners/HandleScheduleTriggered.php

namespace App\Listeners;

use DirectoryTree\Cadence\Events\ScheduleTriggered;

class HandleScheduleTriggered
{
    public function handle(ScheduleTriggered $event): void
    {
        $schedule = $event->schedule;

        // Access the parent model
        $model = $schedule->schedulable;

        // Perform work based on the model type
        if ($model instanceof \App\Models\Report) {
            $model->generate();
        }
    }
}
```

Register it in your `EventServiceProvider` or use event discovery.

## Drivers

Cadence uses a driver-based architecture. Drivers are automatically registered when their backing library is installed.

### Cron

Requires [`dragonmantank/cron-expression`](https://github.com/dragonmantank/cron-expression):

```php
use DirectoryTree\Cadence\Drivers\CronSchedule;

new CronSchedule('0 12 * * *');         // Every day at noon
new CronSchedule('*/15 * * * *');       // Every 15 minutes
new CronSchedule('0 9 * * 1-5');        // Weekdays at 9am
```

### RRULE (php-rrule)

Requires [`rlanvin/php-rrule`](https://github.com/rlanvin/php-rrule):

```php
use DirectoryTree\Cadence\Drivers\RruleSchedule;

new RruleSchedule('FREQ=DAILY;BYDAY=MO,TU,WE,TH,FR');
new RruleSchedule('FREQ=MONTHLY;BYMONTHDAY=1;COUNT=12');
```

### RRULE (Recurr)

Requires [`simshaun/recurr`](https://github.com/simshaun/recurr):

```php
use DirectoryTree\Cadence\Drivers\RecurrSchedule;

new RecurrSchedule('FREQ=WEEKLY;BYDAY=MO,WE,FR');
new RecurrSchedule('FREQ=YEARLY;BYMONTH=1;BYMONTHDAY=1');
```

### Custom Drivers

Create a class that extends the base `Schedule` driver:

```php
namespace App\Drivers;

use Carbon\CarbonInterface;
use DirectoryTree\Cadence\Drivers\Schedule;

class CustomSchedule extends Schedule
{
    protected function resolveNextOccurrence(CarbonInterface $after): ?CarbonInterface
    {
        // Your recurrence logic here
    }
}
```

Then register it in your `AppServiceProvider`:

```php
use App\Drivers\CustomSchedule;
use DirectoryTree\Cadence\Schedule;

Schedule::driver('custom', CustomSchedule::class);
```

## Customizing Drivers

Each driver exposes a static `tap` method to configure the underlying library instance before it's used:

```php
use Cron\CronExpression;
use DirectoryTree\Cadence\Drivers\CronSchedule;

CronSchedule::tap(function (CronExpression $cron) {
    // Configure the CronExpression instance
});
```

```php
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\ArrayTransformerConfig;
use DirectoryTree\Cadence\Drivers\RecurrSchedule;

RecurrSchedule::tap(function (Rule $rule, ArrayTransformer $transformer) {
    $transformer->setConfig(
        (new ArrayTransformerConfig)->enableLastDayOfMonthFix()
    );
});
```

Pass `null` to clear the callback:

```php
RecurrSchedule::tap(null);
```
