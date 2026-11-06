<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invitation $invitation) {}

    public function envelope(): Envelope
    {
        $where = $this->invitation->garage?->name ?? 'Mending';

        return new Envelope(subject: "Votre accès à picstosell ({$where})");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invitation', with: [
            'inv' => $this->invitation,
            'inviter' => $this->invitation->inviter?->name,
            'where' => $this->invitation->garage?->name ?? "l'équipe Mending",
            'url' => $this->invitation->url(),
            'days' => Invitation::DAYS,
        ]);
    }
}
