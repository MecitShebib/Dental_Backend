<?php

namespace Tests\Feature;

use App\Queue\SafeSignalsWorker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SafeSignalsWorkerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_container_hands_out_the_safe_worker_with_its_original_dependencies(): void
    {
        $worker = $this->app->make('queue.worker');

        $this->assertInstanceOf(SafeSignalsWorker::class, $worker);
        $this->assertSame($this->app['queue'], $worker->getManager());
    }

    public function test_signals_are_only_used_when_every_pcntl_function_is_callable(): void
    {
        $expected = extension_loaded('pcntl')
            && collect(SafeSignalsWorker::REQUIRED_FUNCTIONS)->every(fn ($function) => function_exists($function));

        $this->assertSame($expected, SafeSignalsWorker::signalsAvailable());
    }

    public function test_queue_work_processes_a_database_job_end_to_end(): void
    {
        config(['queue.default' => 'database']);
        Cache::forget('safe-worker-test');

        SafeWorkerTestJob::dispatch();
        $this->assertSame(1, DB::table('jobs')->count());

        Artisan::call('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--max-time' => 50]);

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertTrue(Cache::get('safe-worker-test'));
    }
}

class SafeWorkerTestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void
    {
        Cache::forever('safe-worker-test', true);
    }
}
