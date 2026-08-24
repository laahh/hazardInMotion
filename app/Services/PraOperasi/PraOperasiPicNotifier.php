<?php

declare(strict_types=1);

namespace App\Services\PraOperasi;

use App\Services\FonnteService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Kirim notifikasi WA ke PIC perusahaan saat operator ditandai (tindak
 * lanjut) di dashboard Saat Operasi. Daftar PIC memakai sumber yang sama
 * dengan HSECM wa-notify (config('hsecm.wa_recipients')) karena belum ada
 * relasi Perusahaan-PIC formal di database — dicocokkan lewat nama
 * perusahaan (soft match, karena ejaan antar sumber data bisa berbeda).
 */
final class PraOperasiPicNotifier
{
    public function __construct(
        private readonly FonnteService $fonnteService,
    ) {}

    /**
     * @return array{attempted:int, sent:int, failed:int, recipients: list<array{nama:string, no:string, success:bool}>}
     */
    public function notify(
        string $perusahaan,
        string $kodeSid,
        string $nama,
        string $status,
        ?string $catatan,
        string $tanggal,
    ): array {
        $result = ['attempted' => 0, 'sent' => 0, 'failed' => 0, 'recipients' => []];

        $perusahaan = trim($perusahaan);
        if ($perusahaan === '' || trim((string) config('services.fonnte.token', '')) === '') {
            return $result;
        }

        $recipients = $this->recipientsForPerusahaan($perusahaan);
        if ($recipients === []) {
            return $result;
        }

        $message = $this->composeMessage($perusahaan, $kodeSid, $nama, $status, $catatan, $tanggal);

        foreach ($recipients as $recipient) {
            $phone = trim((string) ($recipient['no'] ?? ''));
            if ($phone === '') {
                continue;
            }

            $result['attempted']++;
            try {
                $sendResult = $this->fonnteService->sendMessage($phone, $message);
                $success = (bool) ($sendResult['success'] ?? false);
            } catch (Throwable $e) {
                report($e);
                $success = false;
            }

            $success ? $result['sent']++ : $result['failed']++;
            $result['recipients'][] = [
                'nama' => (string) ($recipient['nama'] ?? '-'),
                'no' => $phone,
                'success' => $success,
            ];

            // jeda singkat antar kirim agar tidak membanjiri Fonnte
            usleep(300000);
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recipientsForPerusahaan(string $perusahaan): array
    {
        $needle = self::normalizeCompanyName($perusahaan);
        if ($needle === '') {
            return [];
        }

        return collect(config('hsecm.wa_recipients', []))
            ->filter(static fn (array $row): bool => self::normalizeCompanyName((string) ($row['perusahaan'] ?? '')) === $needle)
            ->values()
            ->all();
    }

    private static function normalizeCompanyName(string $value): string
    {
        return Str::lower(preg_replace('/[^a-z0-9]/i', '', $value) ?? '');
    }

    private function composeMessage(string $perusahaan, string $kodeSid, string $nama, string $status, ?string $catatan, string $tanggal): string
    {
        $statusLabel = match ($status) {
            'tarik' => 'DITARIK DARI UNIT',
            'perlu_perhatian' => 'PERLU PERHATIAN',
            default => $status !== '' ? Str::upper($status) : '-',
        };

        try {
            $tanggalLabel = Carbon::parse($tanggal, config('app.timezone'))->translatedFormat('d M Y');
        } catch (Throwable) {
            $tanggalLabel = $tanggal;
        }

        $lines = [
            '*Notifikasi Saat Operasi — Tindak Lanjut*',
            $perusahaan,
            '',
            'Operator *'.$nama.'* ('.$kodeSid.') ditandai *'.$statusLabel.'* pada '.$tanggalLabel.'.',
        ];

        if ($catatan !== null && trim($catatan) !== '') {
            $lines[] = 'Catatan supervisor: '.trim($catatan);
        }

        $lines[] = '';
        $lines[] = 'Mohon PIC menindaklanjuti ke lapangan.';
        $lines[] = '';
        $lines[] = '_'.now()->timezone(config('app.timezone', 'Asia/Makassar'))->format('d/m/Y H:i').' WITA_';

        return implode("\n", $lines);
    }
}
