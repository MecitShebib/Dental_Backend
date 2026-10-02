<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\SystemStatusController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminSystemStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['company_id' => null, 'is_project_admin' => true, 'status' => 'active']);
    }

    public function test_test_job_goes_through_the_queue_and_shows_as_processed(): void
    {
        config(['queue.default' => 'database']);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/system')->assertOk()->assertSee('Not running')->assertSee('Not sent yet.');

        $this->actingAs($admin)->post('/admin/system/test-job')->assertRedirect(route('admin.system.show'));
        $this->assertSame(1, DB::table('jobs')->count());
        $this->actingAs($admin)->get('/admin/system')->assertSee('Waiting…');

        Artisan::call('queue:work', ['connection' => 'database', '--stop-when-empty' => true]);

        $this->assertSame(0, DB::table('jobs')->count());
        $this->actingAs($admin)->get('/admin/system')->assertSee('Processed ✓');
    }

    public function test_scheduler_heartbeat_marks_the_cron_as_running(): void
    {
        Cache::forever(SystemStatusController::SCHEDULER_CACHE_KEY, now()->toIso8601String());

        $this->actingAs($this->admin())->get('/admin/system')->assertOk()->assertSee('Running')->assertDontSee('Not running');
    }

    public function test_the_scheduler_records_its_heartbeat(): void
    {
        config(['queue.default' => 'sync']);
        Cache::forget(SystemStatusController::SCHEDULER_CACHE_KEY);

        Artisan::call('schedule:test', ['--name' => 'scheduler-heartbeat']);

        $this->assertNotNull(Cache::get(SystemStatusController::SCHEDULER_CACHE_KEY));
    }

    public function test_only_project_admins_can_open_it(): void
    {
        $this->get('/admin/system')->assertRedirect(route('admin.login'));
    }
}
