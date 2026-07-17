<?php

namespace App\Mail;

use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public OrganizationInvitation $invitation,
        public string $plainToken,
        public ?User $invitedBy = null,
    ) {}

    public function envelope(): Envelope
    {
        $organizationName = (string) ($this->invitation->organization?->name ?? 'la empresa');

        return new Envelope(
            subject: 'Invitación para unirte a '.$organizationName,
        );
    }

    public function content(): Content
    {
        $acceptUrl = route('invitations.accept', ['token' => $this->plainToken]);
        $expiresAt = $this->invitation->expires_at?->timezone('America/Tijuana')->format('Y-m-d H:i');

        return new Content(
            view: 'emails.organization-invitation',
            with: [
                'organizationName' => (string) ($this->invitation->organization?->name ?? 'la empresa'),
                'role' => (string) $this->invitation->role,
                'invitedByName' => $this->invitedBy?->name,
                'expiresAt' => $expiresAt,
                'acceptUrl' => $acceptUrl,
            ],
        );
    }
}
