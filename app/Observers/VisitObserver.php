<?php

namespace App\Observers;

use App\Enums\AttendanceStatus;
use App\Jobs\SendSatisfactionSurveyJob;
use App\Models\Visit;
use App\Services\SatisfactionSurveyService;

/**
 * The moment a visit's attendance_status becomes "attended" (whether set at
 * creation or via a later update, e.g. check-in), send a one-time
 * satisfaction survey invite -- see SatisfactionSurveyService/
 * SendSatisfactionSurveyJob. SatisfactionSurveyService::createForVisit()
 * itself is idempotent (unique visit_id), so a duplicate observer firing
 * (e.g. two saves) can't create two surveys for the same visit.
 *
 * dispatchSync(), not dispatch(): this host has no queue worker process, so
 * anything sent through ::dispatch() sits in the `jobs` table untouched
 * forever (same reasoning as AnalyzeXrayImageJob -- confirmed via
 * production diagnostics that invites were never actually being sent).
 */
class VisitObserver
{
    public function created(Visit $visit): void
    {
        $this->maybeCreateSurvey($visit);
    }

    public function updated(Visit $visit): void
    {
        if ($visit->wasChanged('attendance_status')) {
            $this->maybeCreateSurvey($visit);
        }
    }

    protected function maybeCreateSurvey(Visit $visit): void
    {
        if (($visit->attendance_status?->value ?? $visit->attendance_status) !== AttendanceStatus::Attended->value) {
            return;
        }

        $survey = app(SatisfactionSurveyService::class)->createForVisit($visit);

        if ($survey) {
            SendSatisfactionSurveyJob::dispatchSync($survey);
        }
    }
}
