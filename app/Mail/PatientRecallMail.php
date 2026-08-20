<?php

namespace App\Mail;

use App\Enums\ClientLanguage;
use App\Models\PatientRecall;
use App\Services\PatientRecallService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PatientRecallMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PatientRecall $recall) {}

    public function envelope(): Envelope
    {
        $service = app(PatientRecallService::class);

        return new Envelope(
            subject: $service->emailSubject($this->recall->client),
        );
    }

    public function content(): Content
    {
        $service = app(PatientRecallService::class);
        $client = $this->recall->client;
        $language = $service->languageFor($client);

        return new Content(
            view: 'emails.message-shell',
            with: [
                'companyName' => $client->company?->name ?? '',
                'body' => $service->emailBody($client),
                'isRtl' => $language === ClientLanguage::Arabic,
                'lang' => $language->value,
            ],
        );
    }
}
