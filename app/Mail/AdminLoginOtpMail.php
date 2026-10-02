<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The admin panel's second factor -- unlike OtpCodeMail (sent to the
 * logging-in user's own address), this always goes to a fixed, project-wide
 * inbox (config('services.admin_login.otp_email')) that no admin ever sees
 * or types. Includes who's attempting the login so the inbox holder has
 * enough context to decide whether to hand the code over.
 */
class AdminLoginOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $otp, public ?string $ip) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Admin panel login verification code',
        );
    }

    public function content(): Content
    {
        $who = trim($this->user->name.' ('.$this->user->phone.')');
        $from = $this->ip ? " from IP {$this->ip}" : '';

        return new Content(
            view: 'emails.message-shell',
            with: [
                'companyName' => 'Doctovaria Admin',
                'body' => "A login attempt was made on the admin panel by {$who}{$from}.\n\n"
                    ."Verification code: {$this->otp}\n\n"
                    ."This code expires in 10 minutes. If this wasn't expected, do not share it -- the login will simply expire on its own.",
                'isRtl' => false,
                'lang' => 'en',
            ],
        );
    }
}
