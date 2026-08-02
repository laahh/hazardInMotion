<?php

declare(strict_types=1);

namespace App\Actions\Hsecm;

use App\Models\AutoBannedMasterSod;
use App\Models\Hsecm\HsecmTasklist;
use Illuminate\Support\Facades\Schema;

/**
 * Bangun link wa.me ke Master SOD (filter by site) setelah submit komitmen perbaikan tasklist.
 */
final class HsecmBuildTasklistSubmitMasterSodWaLinksAction
{
    /**
     * @return list<array{nama: string, site: string, no_hp: string, wa_url: string}>
     */
    public function execute(
        HsecmTasklist $tasklist,
        string $submittedByName,
        int $itemCount,
    ): array {
        if (! Schema::hasTable('auto_banned_master_sods')) {
            return [];
        }

        $site = trim((string) ($tasklist->site ?? ''));
        if ($site === '') {
            return [];
        }

        $submittedByName = trim($submittedByName);
        $tasklistUrl = route('hsecm.tasklist.show', ['token' => $tasklist->token], true);
        $siteLabel = $site;
        $perusahaan = trim((string) ($tasklist->perusahaan ?? ''));

        return AutoBannedMasterSod::query()
            ->whereRaw('UPPER(TRIM(site)) = ?', [mb_strtoupper($site)])
            ->orderBy('id')
            ->get(['id', 'nama', 'site', 'no_hp'])
            ->map(function (AutoBannedMasterSod $sod) use (
                $submittedByName,
                $itemCount,
                $tasklistUrl,
                $siteLabel,
                $perusahaan,
            ): ?array {
                $phone = $this->normalizeWhatsappPhone((string) $sod->no_hp);
                if ($phone === '') {
                    return null;
                }

                $nama = trim((string) $sod->nama);
                $message = $this->composeMessage(
                    sodName: $nama !== '' ? $nama : 'SOD',
                    site: $siteLabel,
                    perusahaan: $perusahaan,
                    submittedByName: $submittedByName,
                    itemCount: $itemCount,
                    tasklistUrl: $tasklistUrl,
                );

                return [
                    'nama' => $nama !== '' ? $nama : 'SOD',
                    'site' => trim((string) $sod->site),
                    'no_hp' => trim((string) $sod->no_hp),
                    'wa_url' => 'https://wa.me/'.$phone.'?text='.rawurlencode($message),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function composeMessage(
        string $sodName,
        string $site,
        string $perusahaan,
        string $submittedByName,
        int $itemCount,
        string $tasklistUrl,
    ): string {
        $lines = [
            'Halo '.$sodName.',',
            '',
            'Informasi: komitmen perbaikan telah di-submit.',
            '',
            'Site: '.$site,
        ];

        if ($perusahaan !== '') {
            $lines[] = 'Perusahaan: '.$perusahaan;
        }

        if ($submittedByName !== '') {
            $lines[] = 'Pengirim: '.$submittedByName;
        }

        if ($itemCount > 0) {
            $lines[] = 'Jumlah item: '.$itemCount;
        }

        $lines[] = '';
        $lines[] = 'Link tasklist:';
        $lines[] = $tasklistUrl;
        $lines[] = '';
        $lines[] = 'Mohon ditindaklanjuti.';
        $lines[] = 'Terima kasih.';
        $lines[] = 'PT Berau Coal — HSECM Tasklist';

        return implode("\n", $lines);
    }

    private function normalizeWhatsappPhone(string $phone): string
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
