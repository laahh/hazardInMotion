<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Angka ringkasan karyawan untuk halaman Ringkasan Roster.
 *
 * Dibaca dari tabel lokal dms_roster_karyawan, BUKAN langsung dari OLAP.
 * Sebelumnya kedua query ini menembak Postgres tiap kali halaman dibuka —
 * lambat, dan sering gagal karena koneksi OLAP putus-sambung. Sekarang
 * seluruh kolom yang dibutuhkan (wp_grup, simper_aktif, wajib_cek, site)
 * sudah diisi tahap 1 sinkronisasi, jadi halaman cukup membaca MySQL.
 *
 * Isi tabelnya:
 *   - seluruh baris  = karyawan AKTIF berjabatan operator/driver & mekanik
 *   - wajib_cek=true = yang juga punya SIMPER aktif → inilah base yang dinilai
 *
 * Mengembalikan null bila tabel belum terisi, supaya halaman jatuh ke angka
 * contoh alih-alih menampilkan nol yang menyesatkan.
 */
final class DmsRosterTotalKaryawanReader
{
    private const CACHE_KEY = 'dms_roster:total_karyawan:v2';

    private const CACHE_KEY_SITE = 'dms_roster:total_karyawan_site:v2';

    /**
     * @return array{
     *     total: int,
     *     punya_simper_aktif: int,
     *     tanpa_simper: int,
     *     kelompok: list<array{kelompok:string,total:int,punya_simper_aktif:int,tanpa_simper:int}>,
     * }|null
     */
    public function ambil(): ?array
    {
        return $this->cache(self::CACHE_KEY, function (): ?array {
            $ringkas = DB::table('dms_roster_karyawan')
                ->selectRaw('COUNT(*) AS total')
                ->selectRaw('SUM(simper_aktif = 1) AS punya_simper')
                ->first();

            $total = (int) ($ringkas->total ?? 0);
            if ($total === 0) {
                return null;
            }

            $punyaSimper = (int) ($ringkas->punya_simper ?? 0);

            $perGrup = DB::table('dms_roster_karyawan')
                ->groupBy('wp_grup')
                ->selectRaw('wp_grup')
                ->selectRaw('COUNT(*) AS total')
                ->selectRaw('SUM(simper_aktif = 1) AS punya_simper')
                ->get();

            $kelompok = [];
            foreach ($this->urutanTampil() as $kunci) {
                $baris = $perGrup->firstWhere('wp_grup', $kunci);
                if ($baris === null) {
                    continue;
                }

                $t = (int) $baris->total;
                $s = (int) $baris->punya_simper;
                $kelompok[] = [
                    'kelompok' => $this->labelGrup($kunci),
                    'total' => $t,
                    'punya_simper_aktif' => $s,
                    'tanpa_simper' => $t - $s,
                ];
            }

            return [
                'total' => $total,
                'punya_simper_aktif' => $punyaSimper,
                'tanpa_simper' => $total - $punyaSimper,
                'kelompok' => $kelompok,
            ];
        });
    }

    /**
     * Sebaran per site, dipecah kelompok Working Permit.
     *
     * HANYA menghitung yang punya SIMPER aktif, sama dengan angka headline
     * kartu — kalau tidak, jumlah batang tidak akan sama dengan angka di
     * atasnya. 'total_aktif' dibawa sebagai konteks tooltip.
     *
     * @return list<array{site:string,total:int,total_aktif:int}>|null
     *         plus satu kunci per kelompok WP (a2b, hauler, massal, tanpa)
     */
    public function perSite(): ?array
    {
        return $this->cache(self::CACHE_KEY_SITE, function (): ?array {
            $kunci = $this->urutanTampil();

            $q = DB::table('dms_roster_karyawan')
                ->selectRaw("COALESCE(NULLIF(TRIM(site), ''), '(tanpa site)') AS site")
                ->selectRaw('SUM(simper_aktif = 1) AS total')
                ->selectRaw('COUNT(*) AS total_aktif')
                ->groupByRaw("COALESCE(NULLIF(TRIM(site), ''), '(tanpa site)')")
                ->orderByRaw('SUM(simper_aktif = 1) DESC');

            foreach ($kunci as $g) {
                $q->selectRaw("SUM(simper_aktif = 1 AND wp_grup = ?) AS grp_{$g}", [$g]);
            }

            $rows = $q->get();
            if ($rows->isEmpty()) {
                return null;
            }

            return $rows->map(function (object $r) use ($kunci): array {
                $baris = [
                    'site' => (string) $r->site,
                    'total' => (int) $r->total,
                    'total_aktif' => (int) $r->total_aktif,
                ];

                foreach ($kunci as $g) {
                    $kolom = 'grp_'.$g;
                    $baris[$g] = (int) ($r->{$kolom} ?? 0);
                }

                return $baris;
            })->all();
        });
    }

    /**
     * Kegagalan tidak dikunci selama TTL penuh — tabel bisa saja baru terisi
     * beberapa detik setelah percobaan pertama.
     *
     * @param  callable(): (array<mixed>|null)  $isi
     * @return array<mixed>|null
     */
    private function cache(string $key, callable $isi): ?array
    {
        $ttl = (int) config('dms_roster.total_karyawan.cache_ttl', 600);

        try {
            /** @var array<mixed>|null $hasil */
            $hasil = Cache::remember($key, $ttl, $isi);
        } catch (Throwable $e) {
            Log::warning('DmsRoster ringkasan karyawan gagal: '.$e->getMessage());

            return null;
        }

        if ($hasil === null) {
            Cache::forget($key);
        }

        return $hasil;
    }

    /**
     * Urutan kelompok untuk tampilan: sesuai prioritas di config, lalu
     * 'tanpa' di belakang.
     *
     * @return list<string>
     */
    private function urutanTampil(): array
    {
        /** @var array<string, array<string, mixed>> $grup */
        $grup = config('dms_roster.total_karyawan.wp_grup', []);
        /** @var list<string> $prioritas */
        $prioritas = config('dms_roster.total_karyawan.wp_prioritas', []);

        $urut = array_values(array_filter($prioritas, static fn (string $k): bool => isset($grup[$k])));
        foreach (array_keys($grup) as $k) {
            if (! in_array($k, $urut, true)) {
                $urut[] = $k;
            }
        }
        $urut[] = 'tanpa';

        return $urut;
    }

    private function labelGrup(string $kunci): string
    {
        if ($kunci === 'tanpa') {
            return (string) config('dms_roster.total_karyawan.wp_grup_tanpa_label', 'Tanpa WP unit');
        }

        /** @var array<string, array{label:string}> $grup */
        $grup = config('dms_roster.total_karyawan.wp_grup', []);

        return (string) ($grup[$kunci]['label'] ?? $kunci);
    }
}
