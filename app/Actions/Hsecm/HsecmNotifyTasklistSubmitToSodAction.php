<?php

declare(strict_types=1);

namespace App\Actions\Hsecm;

use App\Mail\HsecmTasklistSubmitMail;
use App\Models\AutoBannedMasterSod;
use App\Models\Hsecm\HsecmTasklist;
use App\Services\Hsecm\HsecmTasklistService;
use App\Services\Hsecm\HsecmWaRecipientRepository;
use App\Services\Mail\ResilientSmtpMailService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Kirim email notifikasi ke SOD terkait setelah submit tasklist berhasil.
 * Sumber penerima: Master SOD (by site) di-resolve email-nya dari HSECM recipients,
 * plus penerima HSECM yang match site + perusahaan tasklist.
 */
final class HsecmNotifyTasklistSubmitToSodAction
{
    public function __construct(
        private readonly HsecmWaRecipientRepository $recipientRepository,
        private readonly HsecmTasklistService $tasklistService,
        private readonly ResilientSmtpMailService $mailService,
    ) {}

    /**
     * @return array{
     *     sent: int,
     *     failed: int,
     *     skipped: int,
     *     recipients: list<array{nama: string, email: string, status: string}>
     * }
     */
    public function execute(
        HsecmTasklist $tasklist,
        string $submittedByName,
        int $itemCount,
    ): array {
        $targets = $this->resolveRecipients($tasklist);
        if ($targets === []) {
            return [
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
                'recipients' => [],
            ];
        }

        $site = trim((string) ($tasklist->site ?? ''));
        $perusahaan = trim((string) ($tasklist->perusahaan ?? ''));
        $tasklistUrl = $this->tasklistService->publicUrl($tasklist);
        $batchSlotLabel = optional($tasklist->batch_slot)?->timezone('Asia/Makassar')?->format('d/m/Y H:i') ?? '';
        $generatedAt = now('Asia/Makassar')->format('d/m/Y H:i');
        $submittedByName = trim($submittedByName);

        $sent = 0;
        $failed = 0;
        $results = [];

        foreach ($targets as $recipient) {
            $email = $this->normalizeEmail($recipient['email'] ?? null);
            $nama = trim((string) ($recipient['nama'] ?? ''));
            if ($email === null) {
                continue;
            }

            try {
                $this->mailService->send(
                    new HsecmTasklistSubmitMail(
                        recipient: $recipient,
                        scope: [
                            'site' => $site,
                            'perusahaan' => $perusahaan,
                            'batch_slot' => $batchSlotLabel,
                        ],
                        submittedByName: $submittedByName,
                        itemCount: $itemCount,
                        tasklistUrl: $tasklistUrl,
                        generatedAt: $generatedAt,
                    ),
                    $email,
                );
                $sent++;
                $results[] = [
                    'nama' => $nama !== '' ? $nama : $email,
                    'email' => $email,
                    'status' => 'sent',
                ];
            } catch (Throwable $e) {
                $failed++;
                $results[] = [
                    'nama' => $nama !== '' ? $nama : $email,
                    'email' => $email,
                    'status' => 'failed',
                ];
                Log::warning('HSECM tasklist submit email gagal dikirim ke SOD.', [
                    'tasklist_id' => $tasklist->id,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => 0,
            'recipients' => $results,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveRecipients(HsecmTasklist $tasklist): array
    {
        /** @var array<string, array<string, mixed>> $byEmail */
        $byEmail = [];

        $site = trim((string) ($tasklist->site ?? ''));
        $perusahaan = trim((string) ($tasklist->perusahaan ?? ''));

        // 1) Penerima HSECM (punya email) untuk site + perusahaan tasklist.
        foreach ($this->matchRecipients($site, $perusahaan) as $recipient) {
            $email = $this->normalizeEmail($recipient['email'] ?? null);
            if ($email === null) {
                continue;
            }
            $byEmail[$email] = $recipient;
        }

        // 2) Master SOD by site → ambil email dari daftar recipients (match nama / no HP).
        if ($site !== '' && Schema::hasTable('auto_banned_master_sods')) {
            $allRecipients = $this->recipientRepository->all();
            $masterSods = AutoBannedMasterSod::query()
                ->whereRaw('UPPER(TRIM(site)) = ?', [mb_strtoupper($site)])
                ->orderBy('id')
                ->get(['nama', 'site', 'no_hp']);

            foreach ($masterSods as $sod) {
                $matched = $this->matchRecipientForMasterSod($sod, $allRecipients);
                if ($matched === null) {
                    continue;
                }
                $email = $this->normalizeEmail($matched['email'] ?? null);
                if ($email === null) {
                    continue;
                }
                $byEmail[$email] = array_merge($matched, [
                    'nama' => trim((string) $sod->nama) !== '' ? trim((string) $sod->nama) : ($matched['nama'] ?? ''),
                    'role' => 'SOD',
                ]);
            }
        }

        return array_values($byEmail);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function matchRecipients(string $site, string $perusahaan): array
    {
        return collect($this->recipientRepository->all())
            ->filter(function (array $r) use ($site, $perusahaan): bool {
                if ($perusahaan !== '' && ! $this->softEqual((string) ($r['perusahaan'] ?? ''), $perusahaan)) {
                    return false;
                }
                $rSite = trim((string) ($r['site'] ?? ''));
                if ($site === '') {
                    return $rSite === '';
                }
                if ($rSite === '') {
                    return true;
                }

                return $this->softEqual($rSite, $site);
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $allRecipients
     * @return array<string, mixed>|null
     */
    private function matchRecipientForMasterSod(AutoBannedMasterSod $sod, array $allRecipients): ?array
    {
        $sodName = $this->normalizeKey((string) $sod->nama);
        $sodPhone = $this->normalizePhoneDigits((string) $sod->no_hp);

        foreach ($allRecipients as $recipient) {
            $email = $this->normalizeEmail($recipient['email'] ?? null);
            if ($email === null) {
                continue;
            }

            $rName = $this->normalizeKey((string) ($recipient['nama'] ?? ''));
            if ($sodName !== '' && $rName !== '' && $sodName === $rName) {
                return $recipient;
            }

            $rPhone = $this->normalizePhoneDigits((string) ($recipient['no'] ?? ''));
            if ($sodPhone !== '' && $rPhone !== '' && $sodPhone === $rPhone) {
                return $recipient;
            }
        }

        return null;
    }

    private function normalizeEmail(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $email = strtolower(trim((string) $value));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $email;
    }

    private function softEqual(string $a, string $b): bool
    {
        return $this->normalizeKey($a) === $this->normalizeKey($b);
    }

    private function normalizeKey(string $value): string
    {
        $value = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $value) ?? ''));

        return str_replace(['.', ',', '-'], '', $value);
    }

    private function normalizePhoneDigits(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }
        if (str_starts_with($digits, '62')) {
            return $digits;
        }
        if (preg_match('/^8\d{8,12}$/', $digits) === 1) {
            return '62'.$digits;
        }

        return $digits;
    }
}
