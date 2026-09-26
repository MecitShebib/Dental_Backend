<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The email counterpart to MobileOtpService::sendOtpSms() -- sent instead of
 * (never alongside) an SMS when MOBILE_OTP_CHANNEL=email, for both the login
 * and forgot-password flows (both share the same OTP challenge/expiry).
 */
class OtpCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $otp) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your verification code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.message-shell',
            with: [
                'companyName' => $this->user->company?->name ?? '',
                'body' => "Your verification code is: {$this->otp}\n\nThis code expires in 10 minutes. If you didn't request this, you can safely ignore this email.",
                'isRtl' => false,
                'lang' => 'en',
            ],
        );
    }
}
