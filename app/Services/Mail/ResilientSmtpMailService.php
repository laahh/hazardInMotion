<?php

declare(strict_types=1);

namespace App\Services\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Kirim email lewat SMTP primary, lalu otomatis fallback ke SMTP backup
 * saat primary gagal (rate limit / quota / temporary SMTP error).
 */
final class ResilientSmtpMailService
{
    /**
     * @param  list<string>|string  $to
     */
    public function send(Mailable $mailable, array|string $to): void
    {
        $chain = $this->mailerChain();
        $lastException = null;

        foreach ($chain as $index => $entry) {
            try {
                if ($entry['from_address'] !== '') {
                    $mailable->from($entry['from_address'], $entry['from_name'] !== '' ? $entry['from_name'] : null);
                }

                Mail::mailer($entry['mailer'])->to($to)->send($mailable);

                if ($index > 0) {
                    Log::warning('Mail sent via backup SMTP after primary failure.', [
                        'mailer' => $entry['mailer'],
                        'to' => $to,
                    ]);
                }

                return;
            } catch (Throwable $exception) {
                $lastException = $exception;

                Log::warning('SMTP mailer failed; trying next mailer if available.', [
                    'mailer' => $entry['mailer'],
                    'to' => $to,
                    'is_rate_limit' => $this->isRateLimitOrTemporaryError($exception),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        throw $lastException ?? new \RuntimeException('No SMTP mailer configured.');
    }

    /**
     * @return list<array{mailer: string, from_address: string, from_name: string}>
     */
    private function mailerChain(): array
    {
        $chain = [
            [
                'mailer' => 'smtp',
                'from_address' => (string) config('mail.from.address', ''),
                'from_name' => (string) config('mail.from.name', ''),
            ],
        ];

        $backupUsername = (string) config('mail.mailers.smtp_backup.username', '');

        if ($backupUsername !== '') {
            $chain[] = [
                'mailer' => 'smtp_backup',
                'from_address' => (string) config('mail.backup_from.address', config('mail.from.address')),
                'from_name' => (string) config('mail.backup_from.name', config('mail.from.name')),
            ];
        }

        return $chain;
    }

    private function isRateLimitOrTemporaryError(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        foreach ([
            'rate',
            'limit',
            'quota',
            'too many',
            'try again',
            'temporary',
            '421',
            '450',
            '451',
            '452',
            '454',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
