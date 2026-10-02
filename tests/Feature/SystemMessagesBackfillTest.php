<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CustomMessage;
use App\Models\MessageGroup;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Services\SystemMessageService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemMessagesBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function runBackfill(): void
    {
        (require database_path('migrations/2026_10_02_000001_backfill_empty_system_message_groups.php'))->up();
    }

    private function subscribe(Company $company, Specialty $specialty): void
    {
        Subscription::create([
            'company_id' => $company->id,
            'specialty_id' => $specialty->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
            'max_branches' => 1,
        ]);
    }

    public function test_an_empty_system_messages_group_is_filled(): void
    {
        $this->seed(SpecialtySeeder::class);
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $this->subscribe($company, $dental);
        $group = MessageGroup::create(['company_id' => $company->id, 'specialty_id' => $dental->id, 'name' => SystemMessageService::GROUP_NAME]);

        $this->runBackfill();

        $this->assertSame(12, $group->messages()->count());
        $this->assertSame(1, MessageGroup::query()->where('company_id', $company->id)->count());
    }

    public function test_a_group_that_already_has_system_messages_is_left_alone(): void
    {
        $this->seed(SpecialtySeeder::class);
        $company = Company::factory()->create();
        $company->subscriptions()->delete();
        $dental = Specialty::query()->where('key', Specialty::DENTAL)->firstOrFail();
        $this->subscribe($company, $dental);
        app(SystemMessageService::class)->seedForCompanySpecialty($company, $dental);
        CustomMessage::query()->where('company_id', $company->id)->where('system_key', 'patient_recall')->delete();

        $this->runBackfill();

        $this->assertSame(9, CustomMessage::query()->where('company_id', $company->id)->count());
    }
}
