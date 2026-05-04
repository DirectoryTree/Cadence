<?php

namespace DirectoryTree\Cadence\Tests\Fixtures;

use DirectoryTree\Cadence\HasSchedules;
use DirectoryTree\Cadence\Schedulable;
use Illuminate\Database\Eloquent\Model;

class SchedulableModel extends Model implements Schedulable
{
    use HasSchedules;

    protected $table = 'schedulable_models';
}
