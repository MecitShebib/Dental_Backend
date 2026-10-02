<?php

namespace App\Jobs;

use App\Models\SatisfactionSurvey;
use App\Services\MessagingService;
use App\Services\SatisfactionSurveyService;
use App\Services\SystemMessageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendSatisfactionSurveyJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public SatisfactionSurvey $survey) {}

    public function handle(SatisfactionSurveyService $surveys, MessagingService $messaging, SystemMessageService $systemMessages): void
    {
        $surveys->sendInvite($this->survey, $messaging, $systemMessages);
    }
}
