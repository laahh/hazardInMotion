<?php

declare(strict_types=1);

namespace App\Services\ControlRoom\Source;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Membaca Google Sheet tasklist validasi TBC via CSV export — lihat
 * plan-OCR.md T1.5. Sheet publik "anyone with link" (dikonfirmasi user),
 * jadi cukup HTTP GET biasa, tanpa service account.
 *
 * STATUS: struktur kolom sheet belum diketahui (Lampiran D #23) — class ini
 * hanya menyediakan fetch+parse CSV generik (array asosiatif per baris,
 * key = header kolom apa adanya). Mapping ke model/tabel snapshot
 * (App\Models\ControlRoom\GsheetTbcSnapshot, belum dibuat) menunggu
 * inventarisasi kolom itu — JANGAN menebak nama kolom di sini.
 */
final class GSheetTbcReader
{
    private readonly string $sheetId;

    private readonly string $gid;

    public function __construct(?string $sheetId = null, ?string $gid = null)
    {
        $this->sheetId = $sheetId ?? (string) config('control-room.gsheet_tbc.sheet_id');
        $this->gid = $gid ?? (string) config('control-room.gsheet_tbc.gid', '0');
    }

    /**
     * @return Collection<int, array<string, string|null>>
     */
    public function fetch(): Collection
    {
        if ($this->sheetId === '') {
            throw new RuntimeException(
                'GSheetTbcReader belum dikonfigurasi — CONTROL_ROOM_GSHEET_TBC_ID kosong di .env. '.
                'Lihat plan-OCR.md Lampiran D pertanyaan #23.'
            );
        }

        $url = "https://docs.google.com/spreadsheets/d/{$this->sheetId}/export?format=csv&gid={$this->gid}";

        $response = Http::timeout(15)->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("GSheetTbcReader: gagal mengambil sheet, HTTP {$response->status()}.");
        }

        $contentType = (string) $response->header('Content-Type');
        if (! str_contains($contentType, 'csv') && ! str_contains($contentType, 'octet-stream')) {
            // Google mengembalikan halaman HTML (login/permission) kalau sheet
            // sudah tidak publik lagi, atau ID/gid salah — jangan biarkan itu
            // silently diparse sebagai CSV kosong.
            throw new RuntimeException(
                "GSheetTbcReader: respons bukan CSV (Content-Type: {$contentType}) — sheet mungkin sudah tidak publik, atau Sheet ID/gid salah."
            );
        }

        return $this->parseCsv($response->body());
    }

    /**
     * @return Collection<int, array<string, string|null>>
     */
    private function parseCsv(string $csv): Collection
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];

        if ($lines === [] || $lines === ['']) {
            return collect();
        }

        $header = str_getcsv((string) array_shift($lines));

        return collect($lines)
            ->filter(fn (string $line): bool => trim($line) !== '')
            ->map(function (string $line) use ($header): array {
                $values = str_getcsv($line);
                $values = array_pad($values, count($header), null);

                return array_combine($header, $values) ?: [];
            })
            ->values();
    }
}
