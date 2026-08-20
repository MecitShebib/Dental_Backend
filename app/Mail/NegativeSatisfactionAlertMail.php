<?php

namespace App\Mail;

use App\Models\SatisfactionSurvey;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NegativeSatisfactionAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SatisfactionSurvey $survey) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Low satisfaction rating from {$this->survey->client?->name}",
        );
    }

    public function content(): Content
    {
        $survey = $this->survey;

        $body = "{$survey->client?->name} rated their recent visit {$survey->rating}/5.".
            ($survey->comment ? "\n\nTheir comment: \"{$survey->comment}\"" : '').
            "\n\nConsider following up with them directly.";

        return new Content(
            view: 'emails.message-shell',
            with: [
                'companyName' => $survey->client?->company?->name ?? '',
                'body' => $body,
                'isRtl' => false,
                'lang' => 'en',
            ],
        );
    }
}
