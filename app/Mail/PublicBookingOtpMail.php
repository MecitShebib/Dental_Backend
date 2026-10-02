<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The public /book/{company} page's verification code, sent by email when
 * MOBILE_OTP_CHANNEL=email (see PublicBookingOtpService::issue()).
 */
class PublicBookingOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $companyName, public string $otp) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your appointment verification code');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.message-shell',
            with: [
                'companyName' => $this->companyName,
                'body' => "Your {$this->companyName} appointment verification code is: {$this->otp}\n\nThis code expires in 10 minutes. If you didn't request this, you can safely ignore this email.",
                'isRtl' => false,
                'lang' => 'en',
            ],
        );
    }
}
