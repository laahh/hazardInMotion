<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Hsecm\HsecmShiftEmailDispatchService;
use Illuminate\Console\Command;
use Throwable;

class HsecmSendMidshiftEmailCommand extends Command
{
    protected $signature = 'hsecm:send-midshift-email
                            {--dry-run : Simulasi tanpa kirim email}
                            {--email= : Batasi hanya ke alamat email ini}
                            {--site= : Scope site jika email belum terdaftar}
                            {--perusahaan= : Scope perusahaan jika email belum terdaftar}
                            {--shift=auto : auto|day|night — day=slot 12:00, night=slot 00:00}';

    protected $description = 'Kirim email Monitoring & Intervensi tengah shift (day:12:00 / night:00:00)';

    public function handle(HsecmShiftEmailDispatchService $dispatchService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlyEmail = $this->optionString('email');
        $shift = strtolower(trim((string) $this->option('shift')));
        $this->info(
            'HSECM midshift email'
            .($dryRun ? ' [DRY-RUN]' : '')
            .($onlyEmail ? " [email={$onlyEmail}]" : '')
            ." [shift={$shift}]"
        );

        try {
            $result = $dispatchService->dispatchMidshift(
                dryRun: $dryRun,
                onlyEmail: $onlyEmail,
                overrideSite: $this->optionString('site'),
                overridePerusahaan: $this->optionString('perusahaan'),
                shift: $shift,
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line($result['message']);
        foreach ($result['details'] as $detail) {
            $mark = ($detail['success'] ?? false) ? 'OK' : 'FAIL';
            $this->line("  [{$mark}] ".($detail['nama'] ?? '-').' <'.($detail['email'] ?? '-').'> — '.($detail['message'] ?? ''));
        }

        return ($result['failed'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function optionString(string $key): ?string
    {
        $value = trim((string) ($this->option($key) ?? ''));

        return $value !== '' ? $value : null;
    }
}
