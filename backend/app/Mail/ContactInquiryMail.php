<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactInquiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly array $inquiry) {}

    public function envelope(): Envelope
    {
        $language = $this->inquiry['language'] === 'en' ? 'en' : 'de';

        return new Envelope(
            from: new Address(
                (string) config('contact.from.address'),
                (string) config('contact.from.name'),
            ),
            replyTo: [new Address($this->inquiry['email'], $this->inquiry['name'])],
            subject: (string) config("contact.subjects.{$language}"),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.contact-inquiry',
            text: 'mail.contact-inquiry-text',
        );
    }
}
