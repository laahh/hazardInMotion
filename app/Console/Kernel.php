<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Sync Telegram messages every minute (monitoring CCTV alerts)
        // This command has built-in lock mechanism to prevent conflicts
        // $schedule->command('telegram:sync --once')
        //     ->everyMinute()
        //     ->withoutOverlapping()
        //     ->runInBackground()
        //     ->appendOutputTo(storage_path('logs/telegram-sync.log'));

        // Screenshot dashboard DOPM & kirim email 3x sehari: pagi, siang, sore
        $schedule->command('dashboard:screenshot-send --slot=pagi')
            ->dailyAt('09:00')
            ->appendOutputTo(storage_path('logs/dashboard-screenshot.log'));
        $schedule->command('dashboard:screenshot-send --slot=siang')
            ->dailyAt('14:00')
            ->appendOutputTo(storage_path('logs/dashboard-screenshot.log'));
        $schedule->command('dashboard:screenshot-send --slot=sore')
            ->dailyAt('17:00')
            ->appendOutputTo(storage_path('logs/dashboard-screenshot.log'));

        // Supervisory alert log: perbarui data alert 3x sehari (pagi, siang, sore) timezone Asia/Makassar
        $schedule->command('supervisory:update-alert-log')
            ->timezone('Asia/Makassar')
            ->dailyAt('06:00')
            ->appendOutputTo(storage_path('logs/supervisory-alert.log'));
        $schedule->command('supervisory:update-alert-log')
            ->timezone('Asia/Makassar')
            ->dailyAt('12:00')
            ->appendOutputTo(storage_path('logs/supervisory-alert.log'));
        $schedule->command('supervisory:update-alert-log')
            ->timezone('Asia/Makassar')
            ->dailyAt('18:00')
            ->appendOutputTo(storage_path('logs/supervisory-alert.log'));

        // DOPM Alert: snapshot + Alert 1/2/3 per IKK (jam ke-1/2/3 sejak start_date, belum IPK). Setiap 30 menit WITA agar Alert 1 tercatat ~30 menit setelah IKK mulai.
        $schedule->command('dopm:alert-snapshot')
            ->timezone('Asia/Makassar')
            ->everyThirtyMinutes()
            ->appendOutputTo(storage_path('logs/dopm-alert-snapshot.log'));

        // DOPM Auto Alert WA: kirim notifikasi WA otomatis setiap jam untuk IKK yang belum ada IPK.
        // Dijalankan setiap jam (pada menit ke-5) setelah dopm:alert-snapshot berjalan.
        // Menggunakan whatsapp-web.js (self-hosted)
        // --limit=10: maksimal 10 pesan per jam untuk menghindari ban
        $schedule->command('dopm:auto-alert-wa --limit=10')
            ->timezone('Asia/Makassar')
            ->hourlyAt(5)
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/dopm-auto-alert-wa.log'));

        // Auto Banned: polling scraping tiap menit untuk deteksi perubahan Pass/Not Pass
        $schedule->command('auto-banned:poll-scrap')
            ->everyMinute()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/auto-banned-poll.log'));

        // Auto Banned: email HSECT — Selasa list awal, reminder harian untuk yang belum banned
        $schedule->command('auto-banned:hsct-email')
            ->timezone(config('auto_banned.hsct.timezone', 'Asia/Makassar'))
            ->dailyAt(config('auto_banned.hsct.send_time', '08:00'))
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/auto-banned-hsct-email.log'));

        // Auto Banned: notifikasi daily banned dari scr_daily_banned (ringkasan + Excel)
        $schedule->command('auto-banned:daily-banned-email')
            ->timezone(config('auto_banned.daily_banned.timezone', 'Asia/Makassar'))
            ->dailyAt(config('auto_banned.daily_banned.send_time', '08:00'))
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/auto-banned-daily-banned-email.log'));

        // Auto Banned: email batch pengajuan unban approved (belum pernah dikirim) ke HSECT
        $schedule->command('auto-banned:hsct-unban-email')
            ->timezone(config('auto_banned.hsct.timezone', 'Asia/Makassar'))
            ->dailyAt(config('auto_banned.hsct.send_time', '08:00'))
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/auto-banned-hsct-unban-email.log'));

        // Auto Banned: verifikasi hourly apakah SID terkirim HSECT sudah terbanned di tabel scrape lain
        $schedule->command('auto-banned:verify-banned')
            ->timezone(config('auto_banned.hsct.timezone', 'Asia/Makassar'))
            ->hourlyAt(10)
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/auto-banned-verify.log'));

        // Generate planning roster dari DOP & IKK setiap 10 menit (hari ini). Skip jika job periode yang sama masih pending/processing.
        $schedule->command('roster:generate-planning')
            ->everyMinutes()
            ->withoutOverlapping(15)
            ->appendOutputTo(storage_path('logs/roster-generate-planning.log'));

        // HSECM: 2 shift / hari
        // Midshift  → 01:00 (slot 00) & 13:00 (slot 12)
        // Endshift  → 07:00 (06 vs 00) & 19:00 (18 vs 12)
        $schedule->command('hsecm:send-midshift-email --shift=night')
            ->timezone('Asia/Makassar')
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/hsecm-midshift-email.log'));

        $schedule->command('hsecm:send-midshift-email --shift=day')
            ->timezone('Asia/Makassar')
            ->dailyAt('13:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/hsecm-midshift-email.log'));

        $schedule->command('hsecm:send-endshift-email --shift=night')
            ->timezone('Asia/Makassar')
            ->dailyAt('07:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/hsecm-endshift-email.log'));

        $schedule->command('hsecm:send-endshift-email --shift=day')
            ->timezone('Asia/Makassar')
            ->dailyAt('19:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/hsecm-endshift-email.log'));

        // HSECM escalate tasklist terbuka: 08 / 14 / 20 / 02
        foreach (['08:00', '14:00', '20:00', '02:00'] as $escalateAt) {
            $schedule->command('hsecm:escalate-open-tasklists')
                ->timezone('Asia/Makassar')
                ->dailyAt($escalateAt)
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/hsecm-escalate-tasklists.log'));
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
