<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class VehicleRenewalReminder extends Mailable
{
    public function __construct(public string $reminderMessage) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Vehicle renewal reminder');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.vehicle-renewal');
    }
}
