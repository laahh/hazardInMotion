<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Simpan daftar "periode kerja 60–70 hari" (DmsRosterRegLiveService, kunci
 * 'mendekati') ke dms_roster_peringatan sebagai sumber notifikasi.
 *
 * Satu baris per (tanggal, kode, SID). Putaran per jam pada hari yang sama
 * memperbarui baris yang ada tanpa menyentuh kolom notif_*, jadi status
 * terkirim tidak hilang. Orang yang hari itu keluar dari rentang (mis. scan
 * terlambat masuk lalu ketahuan sedang cuti) dihapus — hanya bila
 * notifikasinya belum dikirim. Hari-hari sebelumnya tidak disentuh (riwayat).
 */
final class DmsRosterPeringatanStore
{
    public const TABEL = 'dms_roster_peringatan';

    public const KODE_ONSITE = 'onsite';

    private const CHUNK = 500;

    public function siap(): bool
    {
        return Schema::hasTable(self::TABEL);
    }

    /**
     * @param  array<string, mixed>  $hasil  Hasil DmsRosterRegLiveService::segarkan().
     * @return array{disimpan:int,dihapus:int}
     */
    public function simpan(array $hasil): array
    {
        $tanggal = (string) ($hasil['per_iso'] ?? now()->toDateString());
        $baris = self::baris($hasil['mendekati'] ?? [], $tanggal, now()->toDateTimeString());

        return DB::transaction(static function () use ($baris, $tanggal): array {
            foreach (array_chunk($baris, self::CHUNK) as $potong) {
                DB::table(self::TABEL)->upsert($potong, ['tanggal', 'kode', 'kode_sid'], [
                    'nama', 'jabatan', 'kategori', 'perusahaan', 'kode_pt', 'site',
                    'hari_ke', 'sisa_hari', 'mulai_periode', 'tgl_melanggar', 'tgl_banned', 'scan_terakhir', 'updated_at',
                ]);
            }

            $hapus = DB::table(self::TABEL)
                ->where('tanggal', $tanggal)
                ->where('kode', self::KODE_ONSITE)
                ->whereNull('notif_dikirim_pada');
            if ($baris !== []) {
                $hapus->whereNotIn('kode_sid', array_column($baris, 'kode_sid'));
            }

            return ['disimpan' => count($baris), 'dihapus' => $hapus->delete()];
        });
    }

    /**
     * Ubah baris 'mendekati' jadi baris tabel. Murni, tanpa DB.
     *
     * @param  list<array<string, mixed>>  $mendekati
     * @return list<array<string, mixed>>
     */
    public static function baris(array $mendekati, string $tanggal, string $kini): array
    {
        $teks = static function (mixed $v, int $maks): ?string {
            $v = trim((string) $v);

            return $v === '' || $v === '—' ? null : mb_substr($v, 0, $maks);
        };

        $out = [];
        foreach ($mendekati as $m) {
            $sid = strtoupper(trim((string) ($m['sid'] ?? '')));
            if ($sid === '' || isset($out[$sid])) {
                continue;
            }
            $out[$sid] = [
                'tanggal' => $tanggal,
                'kode' => self::KODE_ONSITE,
                'kode_sid' => mb_substr($sid, 0, 20),
                'nama' => $teks($m['nama'] ?? null, 150),
                'jabatan' => $teks($m['jabatan'] ?? null, 150),
                'kategori' => $teks($m['kategori'] ?? null, 50),
                'perusahaan' => $teks($m['perusahaan'] ?? null, 255),
                'kode_pt' => $teks($m['pt'] ?? null, 50),
                'site' => $teks($m['site'] ?? null, 60),
                'hari_ke' => (int) $m['hari_ke'],
                'sisa_hari' => (int) $m['sisa_hari'],
                'mulai_periode' => (string) $m['mulai_iso'],
                'tgl_melanggar' => (string) $m['tgl_melanggar_iso'],
                'tgl_banned' => (string) $m['tgl_banned_iso'],
                'scan_terakhir' => $m['scan_terakhir_iso'] ?? null,
                'created_at' => $kini,
                'updated_at' => $kini,
            ];
        }

        return array_values($out);
    }
}
