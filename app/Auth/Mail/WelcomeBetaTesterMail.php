<?php

namespace App\Auth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeBetaTesterMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $userName,
        public readonly string $tutorialUrl,
        public readonly string $faqsUrl,
        public readonly string $replyToEmail,
        public readonly string $replyToName,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to OVRLOAD',
            replyTo: [
                new Address($this->replyToEmail, $this->replyToName),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.auth.welcome-beta-tester',
            text: 'mail.auth.welcome-beta-tester-text',
        );
    }
}
