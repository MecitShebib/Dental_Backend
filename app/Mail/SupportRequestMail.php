<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the platform's own support inbox (services.support.email) when a
 * clinic user submits the in-app Help & Support form -- a renewal, plan
 * upgrade, extra specialty, AI token top-up, or a general contact request.
 */
class SupportRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public const TYPE_LABELS = [
        'renewal' => 'Subscription renewal',
        'upgrade' => 'Plan upgrade',
        'new_specialty' => 'Subscribe to another specialty',
        'token_topup' => 'AI token top-up',
        'contact' => 'General contact',
    ];

    public function __construct(
        public User $user,
        public string $type,
        public ?string $messageText,
        public ?string $contactPhone,
        public ?string $specialtyName,
        public ?string $tokenAmount,
    ) {}

    public function envelope(): Envelope
    {
        $company = $this->user->company?->name ?? 'Unknown company';

        return new Envelope(
            subject: '['.(self::TYPE_LABELS[$this->type] ?? $this->type)."] {$company} — {$this->user->name}",
            replyTo: $this->user->email ? [new Address($this->user->email, $this->user->name)] : [],
        );
    }

    public function content(): Content
    {
        $user = $this->user;
        $company = $user->company;

        $lines = [
            'Request type: '.(self::TYPE_LABELS[$this->type] ?? $this->type),
            '',
            'Company: '.($company?->name ?? '-').($company ? " (ID {$company->id})" : ''),
            'Company email: '.($company?->email ?: '-'),
            'Company phone: '.($company?->phone ?: '-'),
            '',
            "Requested by: {$user->name} (user ID {$user->id})",
            'Email: '.($user->email ?: '-'),
            'Phone: '.($user->phone ?: '-'),
            'Preferred contact phone: '.($this->contactPhone ?: '-'),
        ];

        if ($this->specialtyName) {
            $lines[] = "Specialty: {$this->specialtyName}";
        }

        if ($this->tokenAmount) {
            $lines[] = "Token amount: {$this->tokenAmount}";
        }

        $lines[] = '';
        $lines[] = 'Message:';
        $lines[] = $this->messageText ?: '(no message)';

        return new Content(
            view: 'emails.message-shell',
            with: [
                'companyName' => 'Doctovaria — Support request',
                'body' => implode("\n", $lines),
                'isRtl' => false,
                'lang' => 'en',
            ],
        );
    }
}
