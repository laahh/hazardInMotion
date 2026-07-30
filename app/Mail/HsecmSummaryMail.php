<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HsecmSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $recipient
     * @param  array{site: string, perusahaan: string, week?: string, year?: string, batch_slot?: string}  $scope
     * @param  array{exposure: list<array<string, mixed>>, gaps: list<array<string, mixed>>}  $emailNarrative
     */
    public function __construct(
        public array $recipient,
        public array $scope,
        public array $emailNarrative,
        public string $dashboardUrl,
        public string $generatedAt,
        public string $mode = 'midshift',
        public string $batchSlotLabel = '',
        public int $escalateCount = 0,
        public string $ctaLabel = 'Buka Dashboard',
        public string $monitoringUrl = '',
        public string $tasklistUrl = '',
    ) {}

    public function envelope(): Envelope
    {
        $site = ($this->scope['site'] ?? '') !== '' ? $this->scope['site'] : 'Semua Site';
        $company = ($this->scope['perusahaan'] ?? '') !== '' ? $this->scope['perusahaan'] : 'Semua Perusahaan';

        $prefix = match ($this->mode) {
            'endshift' => 'Pertengahan Shift — Tasklist Monitoring & Intervensi',
            'escalate' => 'Escalate #'.$this->escalateCount.' — Tasklist belum closed',
            default => 'Daily Monitoring & Intervensi',
        };

        $fromAddress = (string) config('mail.from.address', 'noreply@beraucoal.co.id');
        $fromName = (string) config('hsecm.mail_from_name', 'Daily Notification');

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: $prefix.' — '.$site.' · '.$company,
        );
    }

    public function content(): Content
    {
        $view = match ($this->mode) {
            'endshift' => 'emails.hsecm-endshift',
            'escalate' => 'emails.hsecm-escalate',
            default => 'emails.hsecm-summary',
        };

        return new Content(view: $view);
    }
}
