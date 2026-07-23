<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HsecmSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $recipient
     * @param  array{site: string, perusahaan: string, week: string, year: string}  $scope
     * @param  array{exposure: list<array<string, mixed>>, gaps: list<array<string, mixed>>}  $emailNarrative
     */
    public function __construct(
        public array $recipient,
        public array $scope,
        public array $emailNarrative,
        public string $dashboardUrl,
        public string $generatedAt,
    ) {}

    public function envelope(): Envelope
    {
        $site = ($this->scope['site'] ?? '') !== '' ? $this->scope['site'] : 'Semua Site';
        $company = ($this->scope['perusahaan'] ?? '') !== '' ? $this->scope['perusahaan'] : 'Semua Perusahaan';

        return new Envelope(
            subject: 'Daily Monitoring & Intervensi — '.$site.' · '.$company,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.hsecm-summary',
        );
    }
}
