<?php

use App\Models\Company;
use App\Models\CustomMessage;
use App\Models\MessageGroup;
use App\Models\Specialty;
use App\Services\SystemMessageService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fills the "System Messages" group for every company+specialty that has
     * a subscription but no system message yet -- groups created before
     * SystemMessageService seeded the messages themselves were left empty,
     * and seeding only runs when a subscription is created. A group that
     * already holds at least one system message is left alone, so wording a
     * clinic deleted on purpose is not brought back.
     */
    public function up(): void
    {
        $service = app(SystemMessageService::class);

        $pairs = DB::table('subscriptions')
            ->whereNotNull('specialty_id')
            ->select('company_id', 'specialty_id')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            $company = Company::query()->find($pair->company_id);
            $specialty = Specialty::query()->find($pair->specialty_id);
            if (! $company || ! $specialty) {
                continue;
            }

            $groupIds = MessageGroup::query()
                ->where('company_id', $company->id)
                ->where('specialty_id', $specialty->id)
                ->where('name', SystemMessageService::GROUP_NAME)
                ->pluck('id');

            $hasSystemMessages = CustomMessage::query()
                ->whereIn('message_group_id', $groupIds)
                ->whereNotNull('system_key')
                ->exists();

            if (! $hasSystemMessages) {
                $service->seedForCompanySpecialty($company, $specialty);
            }
        }
    }

    public function down(): void
    {
        // Data backfill only -- the seeded rows are ordinary editable messages.
    }
};
