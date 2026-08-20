<?php

namespace App\Mail;

use App\Models\InventoryItem;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LowStockAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public InventoryItem $item) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Low stock alert: {$this->item->name}",
        );
    }

    public function content(): Content
    {
        $item = $this->item;

        $body = "The stock level for \"{$item->name}\" has dropped to {$item->quantity_on_hand} {$item->unit}, ".
            "at or below the reorder threshold of {$item->reorder_threshold} {$item->unit}.";

        if ($item->reorder_quantity) {
            $body .= "\nSuggested reorder quantity: {$item->reorder_quantity} {$item->unit}.";
        }

        return new Content(
            view: 'emails.message-shell',
            with: [
                'companyName' => $item->company?->name ?? '',
                'body' => $body,
                'isRtl' => false,
                'lang' => 'en',
            ],
        );
    }
}
