<?php

namespace App\Jobs;

use App\Models\SatisfactionSurvey;
use App\Services\MessageTemplateService;
use App\Services\MessagingService;
use App\Services\SatisfactionSurveyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendSatisfactionSurveyJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public SatisfactionSurvey $survey) {}

    public function handle(SatisfactionSurveyService $surveys, MessagingService $messaging, MessageTemplateService $templates): void
    {
        $surveys->sendInvite($this->survey, $messaging, $templates);
    }
}
