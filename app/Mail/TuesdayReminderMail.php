<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TuesdayReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $manager,
        public string $weekStart
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[NVT] Action Required: Schedule not submitted (week of ' . $this->weekStart . ')',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.tuesday-reminder',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
