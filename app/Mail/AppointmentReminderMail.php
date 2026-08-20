<?php

namespace App\Mail;

use App\Enums\ClientLanguage;
use App\Models\Appointment;
use App\Services\AppointmentReminderService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Appointment $appointment) {}

    public function envelope(): Envelope
    {
        $service = app(AppointmentReminderService::class);

        return new Envelope(
            subject: $service->emailSubject($this->appointment),
        );
    }

    public function content(): Content
    {
        $service = app(AppointmentReminderService::class);
        $language = $service->languageFor($this->appointment);
        $appointment = $this->appointment;

        return new Content(
            view: 'emails.message-shell',
            with: [
                'companyName' => $appointment->company?->name ?? '',
                'body' => $service->emailBody($appointment),
                'isRtl' => $language === ClientLanguage::Arabic,
                'lang' => $language->value,
            ],
        );
    }
}
