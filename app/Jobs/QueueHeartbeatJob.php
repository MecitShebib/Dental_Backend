<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Harmless test job sent from Admin > System Status. When the cron-driven
 * queue worker processes it, it records when -- proving the whole chain
 * (cron -> schedule:run -> queue:work -> job) works end to end.
 */
class QueueHeartbeatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const CACHE_KEY = 'system:queue_heartbeat';

    public function __construct(public string $dispatchedAt) {}

    public function handle(): void
    {
        Cache::forever(self::CACHE_KEY, [
            'dispatched_at' => $this->dispatchedAt,
            'processed_at' => now()->toIso8601String(),
        ]);

        Log::info('Queue heartbeat job processed.', ['dispatched_at' => $this->dispatchedAt]);
    }
}
