<?php

namespace App\Jobs;

use App\Models\PatientRecall;
use App\Services\PatientRecallService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPatientRecallJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public PatientRecall $recall) {}

    public function handle(PatientRecallService $recalls): void
    {
        $recalls->send($this->recall);
    }
}
