<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Hsecm\HsecmShiftEmailDispatchService;
use Illuminate\Console\Command;
use Throwable;

class HsecmSendEndshiftEmailCommand extends Command
{
    protected $signature = 'hsecm:send-endshift-email
                            {--dry-run : Simulasi tanpa kirim email/WA / buat tasklist}
                            {--email= : Batasi hanya ke alamat email ini}
                            {--site= : Scope site jika email belum terdaftar}
                            {--perusahaan= : Scope perusahaan jika email belum terdaftar}
                            {--shift=auto : auto|day|night — day=18vs12, night=06vs00}
                            {--channel=both : email|wa|both — email SMTP dan/atau WA Fonnte}';

    protected $description = 'Kirim notifikasi pasca-shift (email/WA Fonnte) + buat/kirim link Tasklist (snapshot latest batch_slot)';

    public function handle(HsecmShiftEmailDispatchService $dispatchService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlyEmail = $this->optionString('email');
        $shift = strtolower(trim((string) $this->option('shift')));
        $channel = strtolower(trim((string) $this->option('channel')));
        $this->info(
            'HSECM endshift'
            .($dryRun ? ' [DRY-RUN]' : '')
            .($onlyEmail ? " [email={$onlyEmail}]" : '')
            ." [shift={$shift}]"
            ." [channel={$channel}]"
        );

        try {
            $result = $dispatchService->dispatchEndshift(
                dryRun: $dryRun,
                onlyEmail: $onlyEmail,
                overrideSite: $this->optionString('site'),
                overridePerusahaan: $this->optionString('perusahaan'),
                shift: $shift,
                channel: $channel,
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line($result['message']);
        foreach ($result['details'] as $detail) {
            $mark = ($detail['success'] ?? false) ? 'OK' : 'FAIL';
            $this->line("  [{$mark}] ".($detail['nama'] ?? $detail['scope'] ?? '-').' <'.($detail['email'] ?? '-').'> — '.($detail['message'] ?? ''));
        }

        return ($result['failed'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function optionString(string $key): ?string
    {
        $value = trim((string) ($this->option($key) ?? ''));

        return $value !== '' ? $value : null;
    }
}
