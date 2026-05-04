<?php

namespace DirectoryTree\Cadence;

use InvalidArgumentException;

class Cadence
{
    /**
     * The registered schedule drivers.
     *
     * @var array<string, class-string<ScheduleDriver>>
     */
    protected static array $drivers = [];

    /**
     * Register a schedule driver.
     *
     * @param  class-string<ScheduleDriver>  $driver
     */
    public static function register(string $type, string $driver): void
    {
        static::$drivers[$type] = $driver;
    }

    /**
     * Resolve a schedule driver instance from a stored type and expression.
     */
    public static function getDriver(string $type): string
    {
        $driverClass = static::$drivers[$type] ?? null;

        if (! $driverClass) {
            throw new InvalidArgumentException(
                "Unknown schedule driver type: {$type}"
            );
        }

        return $driverClass;
    }

    /**
     * Resolve the type string for a driver instance.
     */
    public static function getDriverType(ScheduleDriver $driver): string
    {
        $type = array_search(get_class($driver), static::$drivers, true);

        if ($type === false) {
            throw new InvalidArgumentException(
                'Unregistered schedule driver: '.get_class($driver)
            );
        }

        return $type;
    }
}
