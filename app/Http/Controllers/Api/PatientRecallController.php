<?php

namespace App\Http\Controllers\Api;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\PatientRecallService;
use Illuminate\Http\Request;

class PatientRecallController extends Controller
{
    /**
     * Clients currently due for a recall within the requesting user's own
     * company -- same eligibility rules the scheduled command uses, but
     * read-only so staff can see who's overdue before the SMS/email goes out.
     */
    public function index(Request $request, PatientRecallService $recalls)
    {
        $companyId = $request->user()->company_id;

        $rows = $recalls->dueRecalls()
            ->filter(fn (array $due) => $due['client']->company_id === $companyId)
            ->map(fn (array $due) => [
                'client_id' => $due['client']->id,
                'client_name' => $due['client']->name,
                'client_phone' => $due['client']->phone,
                'last_visit_date' => $due['due_at']->toDateString(),
                'days_overdue' => (int) $due['due_at']->diffInDays(now()),
            ])
            ->sortByDesc('days_overdue')
            ->values();

        return $this->success($rows);
    }

    /**
     * Manually send a recall to one client right now, bypassing the
     * eligibility gate -- for staff who want to nudge a patient ad hoc
     * rather than wait for the daily cron.
     */
    public function send(Request $request, Client $client, PatientRecallService $recalls)
    {
        $visit = $client->visits()
            ->where('attendance_status', AttendanceStatus::Attended->value)
            ->latest('visit_date')
            ->latest('id')
            ->first();

        if (! $visit) {
            return $this->success(null, 'Client has no completed visit to recall from.', 422);
        }

        $recall = $recalls->claim($client, $visit, now());

        if (! $recall) {
            return $this->success(null, 'A recall has already been sent for this client\'s latest visit.', 422);
        }

        $recalls->send($recall);

        return $this->success(null, 'Recall sent.');
    }
}
