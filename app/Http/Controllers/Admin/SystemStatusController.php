<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\QueueHeartbeatJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin > System Status: is the cron running schedule:run, and is the queue
 * actually processing jobs? (The host has no SSH, so this is the only place
 * to see it.) "Send test job" dispatches QueueHeartbeatJob.
 */
class SystemStatusController extends Controller
{
    public const SCHEDULER_CACHE_KEY = 'system:scheduler_last_run';

    public function show()
    {
        $schedulerLastRun = Cache::get(self::SCHEDULER_CACHE_KEY);
        $heartbeat = Cache::get(QueueHeartbeatJob::CACHE_KEY);

        return view('admin.system.show', [
            'schedulerLastRun' => $schedulerLastRun ? Carbon::parse($schedulerLastRun) : null,
            'pendingJobs' => DB::table('jobs')->count(),
            'failedJobs' => DB::table('failed_jobs')->count(),
            'oldestPendingJob' => ($oldest = DB::table('jobs')->min('created_at')) ? Carbon::createFromTimestamp($oldest) : null,
            'recentFailures' => DB::table('failed_jobs')->latest('failed_at')->limit(5)->get(['uuid', 'queue', 'failed_at', 'exception']),
            'heartbeat' => $heartbeat,
            'pendingHeartbeat' => Cache::get('system:queue_heartbeat_pending'),
        ]);
    }

    public function testJob()
    {
        $dispatchedAt = now()->toIso8601String();
        Cache::forever('system:queue_heartbeat_pending', $dispatchedAt);
        QueueHeartbeatJob::dispatch($dispatchedAt);

        return redirect()->route('admin.system.show')->with('status', 'Test job added to the queue. It should be processed within about a minute -- refresh this page.');
    }
}
