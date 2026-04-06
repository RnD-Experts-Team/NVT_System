<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MondayReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $manager)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[NVT] Reminder: Please submit this week\'s schedule',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.monday-reminder',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
