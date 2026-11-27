<?php

namespace App\Services\PetaRisiko\Drm;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pengecualian baseline Overview (drm_baseline_pengecualian) — lihat migrasi 2026_11_27_120000. Baris
 * (kelompok aktivitas × bahaya-risiko unik, identitas kel+hid) yang di-nonaktifkan di sini dikeluarkan LANGSUNG
 * (tanpa menunggu peta-risiko:sync) dari hitungan kartu & modal "Tidak Teridentifikasi (Open Risk)" dkk di
 * Overview — lihat index.blade.php (fetch daftarAktif() lalu disaring di JS). Riwayat tetap tersimpan: baris yang
 * "dikembalikan aktif" tidak dihapus, cuma aktif=false + dikembalikan_oleh/dikembalikan_pada diisi.
 */
class DrmBaselinePengecualian
{
    public const TABEL = 'drm_baseline_pengecualian';

    public static function siap(): bool
    {
        return Schema::hasTable(self::TABEL);
    }

    /**
     * Daftar baris aktif (dikecualikan) — dipakai Overview untuk menyaring rincian (kel+hid) DAN menambal
     * (subtract) kartu KPI / treemap kelompok aktivitas secara langsung (lv/kode/inc/tr — lihat catatan migrasi).
     *
     * @return list<array{kel: string, hid: string, lv: string, kode: string, inc: bool, tr: bool}>
     */
    public function daftarAktif(): array
    {
        if (! self::siap()) {
            return [];
        }

        return DB::table(self::TABEL)->where('aktif', true)->get(['kel', 'hid', 'lv', 'kode', 'inc', 'tr'])
            ->map(fn ($r) => ['kel' => (string) $r->kel, 'hid' => (string) $r->hid, 'lv' => (string) $r->lv,
                'kode' => (string) $r->kode, 'inc' => (bool) $r->inc, 'tr' => (bool) $r->tr])->all();
    }

    /**
     * Tandai satu baris (kel+hid) sebagai dikecualikan. Tidak menimpa baris aktif lain dengan kel+hid sama —
     * selalu baris baru, supaya setiap aksi flag punya jejak riwayatnya sendiri.
     */
    public function tandai(string $kel, string $hid, string $aktivitas, string $sub, string $bahaya, string $alasan,
        string $lv, string $kode, bool $inc, bool $tr, ?string $oleh): int
    {
        return DB::table(self::TABEL)->insertGetId([
            'kel' => $kel, 'hid' => $hid, 'aktivitas' => $aktivitas, 'sub' => $sub, 'bahaya' => $bahaya,
            'alasan' => $alasan, 'lv' => $lv, 'kode' => $kode, 'inc' => $inc, 'tr' => $tr,
            'aktif' => true, 'dibuat_oleh' => $oleh, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Kembalikan satu baris pengecualian jadi aktif lagi (dihitung ulang di Open Risk). Riwayatnya tetap ada. */
    public function kembalikan(int $id, ?string $oleh): bool
    {
        return DB::table(self::TABEL)->where('id', $id)->where('aktif', true)->update([
            'aktif' => false, 'dikembalikan_oleh' => $oleh, 'dikembalikan_pada' => now(), 'updated_at' => now(),
        ]) > 0;
    }

    /** Riwayat (aktif + sudah dikembalikan), terbaru dulu, untuk halaman riwayat. */
    public function riwayat(int $perHalaman = 50): LengthAwarePaginator
    {
        return DB::table(self::TABEL)->orderByDesc('id')->paginate($perHalaman);
    }
}
