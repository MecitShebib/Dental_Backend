<?php

namespace Tests\Feature;

use App\Mail\AnomalousAccessAlertMail;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DetectAnomalousAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_viewing_many_distinct_patients_in_an_hour_triggers_an_alert(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        for ($i = 0; $i < 35; $i++) {
            AuditLog::create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'action' => 'viewed',
                'auditable_type' => Client::class,
                'auditable_id' => $i + 1,
            ]);
        }

        $this->artisan('kvkk:detect-anomalous-access')->assertSuccessful();

        Mail::assertSent(AnomalousAccessAlertMail::class, fn ($mail) => $mail->user->id === $user->id && $mail->distinctClientsViewed === 35);
    }

    public function test_normal_viewing_volume_does_not_trigger_an_alert(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            AuditLog::create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'action' => 'viewed',
                'auditable_type' => Client::class,
                'auditable_id' => $i + 1,
            ]);
        }

        $this->artisan('kvkk:detect-anomalous-access')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_views_older_than_an_hour_are_ignored(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        for ($i = 0; $i < 35; $i++) {
            $log = AuditLog::create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'action' => 'viewed',
                'auditable_type' => Client::class,
                'auditable_id' => $i + 1,
            ]);
            $log->forceFill(['created_at' => now()->subHours(2)])->save();
        }

        $this->artisan('kvkk:detect-anomalous-access')->assertSuccessful();

        Mail::assertNothingSent();
    }
}
