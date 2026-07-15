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
     * @param  list<array{label: string, value: string|int|float, icon: string, hint: string, tone: string}>  $kpis
     * @param  list<array{key: string, label: string, count: int}>  $datasets
     */
    public function __construct(
        public array $recipient,
        public array $scope,
        public array $kpis,
        public array $datasets,
        public int $totalRecords,
        public string $dashboardUrl,
        public string $generatedAt,
    ) {}

    public function envelope(): Envelope
    {
        $site = $this->scope['site'] !== '' ? $this->scope['site'] : 'Semua Site';
        $company = $this->scope['perusahaan'] !== '' ? $this->scope['perusahaan'] : 'Semua Perusahaan';

        return new Envelope(
            subject: 'HSECM Monitoring Summary — '.$site.' · '.$company,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.hsecm-summary',
        );
    }
}
