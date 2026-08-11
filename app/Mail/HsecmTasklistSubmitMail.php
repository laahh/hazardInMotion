<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HsecmTasklistSubmitMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{nama?: string, role?: string, email?: string}  $recipient
     * @param  array{site: string, perusahaan: string, batch_slot?: string}  $scope
     */
    public function __construct(
        public array $recipient,
        public array $scope,
        public string $submittedByName,
        public int $itemCount,
        public string $tasklistUrl,
        public string $generatedAt,
    ) {}

    public function envelope(): Envelope
    {
        $site = ($this->scope['site'] ?? '') !== '' ? $this->scope['site'] : 'Semua Site';
        $company = ($this->scope['perusahaan'] ?? '') !== '' ? $this->scope['perusahaan'] : 'Semua Perusahaan';

        $fromAddress = (string) config('mail.from.address', 'noreply@beraucoal.co.id');
        $fromName = (string) config('hsecm.mail_from_name', 'Daily Notification');

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: 'Tasklist Submitted — '.$site.' · '.$company,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.hsecm-tasklist-submit');
    }
}
