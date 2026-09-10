<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AnomalousAccessAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public int $distinctClientsViewed) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Unusual patient record access by {$this->user->name}",
        );
    }

    public function content(): Content
    {
        $body = "{$this->user->name} viewed {$this->distinctClientsViewed} different patient records in the last hour.".
            "\n\nThis is flagged automatically as part of the KVKK veri güvenliği (data security) monitoring -- it may be entirely normal (e.g. a receptionist working through a busy day), but is also the pattern a bulk data-export attempt or a compromised account would produce. Worth a quick check with {$this->user->name} if this wasn't expected.";

        return new Content(
            view: 'emails.message-shell',
            with: [
                'companyName' => $this->user->company?->name ?? '',
                'body' => $body,
                'isRtl' => false,
                'lang' => 'en',
            ],
        );
    }
}
