<?php

declare(strict_types=1);

namespace App\Console\Commands\EmergencyResponse;

use App\Models\EmergencyResponse\Notification\NotificationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RetryFailedNotifications extends Command
{
    protected $signature = 'emergency-response:retry-failed-notifications';

    protected $description = 'Coba kirim ulang notifikasi email yang gagal dalam 24 jam terakhir';

    public function handle(): int
    {
        $failedLogs = NotificationLog::query()
            ->where('channel', 'email')
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->with('notification')
            ->get();

        $retried = 0;

        foreach ($failedLogs as $log) {
            if (! $log->notification) {
                continue;
            }

            try {
                $body = nl2br(e($log->notification->message)).($log->notification->link_url ? '<p><a href="'.$log->notification->link_url.'">Lihat detail</a></p>' : '');

                Mail::html($body, function ($mail) use ($log): void {
                    $mail->to($log->recipient)->subject($log->notification->title);
                });

                $log->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null]);
                $retried++;
            } catch (\Throwable $e) {
                Log::error('RetryFailedNotifications gagal: '.$e->getMessage());
                $log->update(['error_message' => $e->getMessage()]);
            }
        }

        $this->info("{$retried} notifikasi berhasil dikirim ulang dari {$failedLogs->count()} yang gagal.");

        return self::SUCCESS;
    }
}
