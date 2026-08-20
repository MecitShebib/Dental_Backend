<?php

namespace App\Mail;

use App\Enums\ClientLanguage;
use App\Models\SatisfactionSurvey;
use App\Services\MessageTemplateService;
use App\Services\SatisfactionSurveyService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SatisfactionSurveyInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SatisfactionSurvey $survey) {}

    public function envelope(): Envelope
    {
        $rendered = app(SatisfactionSurveyService::class)->renderInvite($this->survey, app(MessageTemplateService::class), 'email');

        return new Envelope(subject: $rendered['subject'] ?? '');
    }

    public function content(): Content
    {
        $service = app(SatisfactionSurveyService::class);
        $rendered = $service->renderInvite($this->survey, app(MessageTemplateService::class), 'email');
        $language = $service->languageFor($this->survey);

        return new Content(
            view: 'emails.message-shell',
            with: [
                'companyName' => $this->survey->client?->company?->name ?? '',
                'body' => $rendered['body'],
                'isRtl' => $language === ClientLanguage::Arabic,
                'lang' => $language->value,
            ],
        );
    }
}
