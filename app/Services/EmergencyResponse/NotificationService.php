<?php

declare(strict_types=1);

namespace App\Services\EmergencyResponse;

use App\Models\EmergencyResponse\Notification\Notification;
use App\Models\EmergencyResponse\Notification\NotificationLog;
use App\Models\EmergencyResponse\Notification\NotificationPreference;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Titik tunggal pengiriman notifikasi in-app + email untuk seluruh modul
 * Emergency Response, menghormati preferensi per user (er_notification_preferences)
 * dan mencatat setiap percobaan pengiriman ke er_notification_logs.
 */
class NotificationService
{
    public function notifyUsers(iterable $users, string $type, string $title, string $message, ?string $linkUrl = null): void
    {
        foreach ($users as $user) {
            $this->notifyUser($user, $type, $title, $message, $linkUrl);
        }
    }

    public function notifyUser(User $user, string $type, string $title, string $message, ?string $linkUrl = null): void
    {
        $preference = NotificationPreference::firstOrCreate(['user_id' => $user->id]);

        $notification = null;

        if ($preference->in_app_enabled) {
            $notification = Notification::create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'link_url' => $linkUrl,
                'is_read' => false,
                'created_at' => now(),
            ]);

            NotificationLog::create([
                'notification_id' => $notification->id,
                'channel' => 'in_app',
                'recipient' => (string) $user->id,
                'status' => 'sent',
                'sent_at' => now(),
                'created_at' => now(),
            ]);
        }

        if ($preference->email_enabled && $user->email) {
            $this->sendEmail($user, $title, $message, $linkUrl, $notification?->id);
        }
    }

    public function notifyRole(string $roleSlug, string $type, string $title, string $message, ?string $linkUrl = null): void
    {
        $role = Role::where('slug', $roleSlug)->first();
        if (! $role) {
            return;
        }

        $this->notifyUsers($role->users, $type, $title, $message, $linkUrl);
    }

    private function sendEmail(User $user, string $title, string $message, ?string $linkUrl, ?string $notificationId): void
    {
        $body = nl2br(e($message)).($linkUrl ? '<p><a href="'.$linkUrl.'">Lihat detail</a></p>' : '');

        try {
            Mail::html($body, function ($mail) use ($user, $title): void {
                $mail->to($user->email)->subject($title);
            });

            NotificationLog::create([
                'notification_id' => $notificationId,
                'channel' => 'email',
                'recipient' => $user->email,
                'status' => 'sent',
                'sent_at' => now(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('NotificationService gagal mengirim email: '.$e->getMessage());

            NotificationLog::create([
                'notification_id' => $notificationId,
                'channel' => 'email',
                'recipient' => $user->email,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'created_at' => now(),
            ]);
        }
    }
}
