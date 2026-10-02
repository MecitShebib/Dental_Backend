<?php

namespace App\Queue;

use Illuminate\Queue\Worker;

/**
 * Laravel's queue worker decides whether to use POSIX signals (graceful stop,
 * per-job timeouts) with `extension_loaded('pcntl')` alone. The production
 * shared host has the pcntl extension loaded but its functions listed in
 * disable_functions, so `queue:work` died with "Call to undefined function
 * pcntl_signal()". This worker only uses signals when every function it
 * needs is actually callable; otherwise it simply runs without them (no
 * per-job timeout -- the scheduler's --max-time and withoutOverlapping still
 * bound each run).
 */
class SafeSignalsWorker extends Worker
{
    public const REQUIRED_FUNCTIONS = ['pcntl_async_signals', 'pcntl_signal', 'pcntl_alarm'];

    /**
     * Turns the container's stock worker into this class, keeping every
     * property it was built with (queue manager, events, exception handler,
     * maintenance/reset callbacks, ...).
     */
    public static function fromWorker(Worker $worker): static
    {
        $instance = (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();

        foreach ((fn () => get_object_vars($this))->call($worker) as $property => $value) {
            $instance->{$property} = $value;
        }

        return $instance;
    }

    public static function signalsAvailable(): bool
    {
        if (! extension_loaded('pcntl')) {
            return false;
        }

        foreach (self::REQUIRED_FUNCTIONS as $function) {
            if (! function_exists($function)) {
                return false;
            }
        }

        return true;
    }

    protected function supportsAsyncSignals()
    {
        return static::signalsAvailable();
    }
}
