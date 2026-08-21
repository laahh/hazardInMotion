<?php

declare(strict_types=1);

namespace App\Mail\OhsDashboard;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class PortalDigestMail extends Mailable
{
    /**
     * @param  array<string, mixed>  $overview
     */
    public function __construct(
        public array $overview,
        public string $portalUrl,
        public bool $isTest,
        public bool $includeLeave,
        public bool $includeTracker,
        public bool $includeLeaderboard,
        public int $tableLimit,
        public int $leaderboardLimit,
        public string $subjectLine,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            from: new Address(
                (string) config('mail.from.address'),
                (string) config('ohs-dashboard.scheduler.from_name', 'OHS Portal Scheduler'),
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'OhsDashboard.emails.digest',
        );
    }
}
