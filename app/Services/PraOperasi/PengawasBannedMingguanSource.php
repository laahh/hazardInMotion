<?php

declare(strict_types=1);

namespace App\Services\PraOperasi;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Pembaca riwayat banned MINGGUAN pengawas dari validasi_status_banned_pengawas_weekly.
 *
 * Struktur tabel itu belum pernah dilihat dari kode, jadi nama kolom dicocokkan saat dijalankan
 * dari beberapa kandidat lazim. Bila tabel tidak ada atau kolom wajib (SID, status, periode) tidak
 * ditemukan, sumber ini dianggap tidak tersedia dan form berjalan dengan data harian saja.
 *
 * Periode minggu: kolom tanggal awal minggu bila ada; bila tidak, dihitung dari nomor minggu ISO + tahun.
 */
final class PengawasBannedMingguanSource
{
    public const TABEL = 'validasi_status_banned_pengawas_weekly';

    private const KANDIDAT = [
        'sid' => ['sid', 'kode_sid', 'sid_pelapor'],
        'status' => ['status_banned', 'status'],
        'mulai' => ['minggu_mulai', 'week_start', 'tanggal_mulai', 'start_date', 'tanggal_awal', 'periode_mulai', 'minggu_awal', 'tanggal_kerja', 'tanggal'],
        'selesai' => ['minggu_selesai', 'week_end', 'tanggal_selesai', 'end_date', 'tanggal_akhir', 'periode_selesai', 'minggu_akhir'],
        'minggu' => ['minggu_ke', 'iso_week', 'week_number', 'week', 'minggu'],
        'tahun' => ['iso_year', 'tahun', 'year'],
        'nama' => ['nama', 'karyawan', 'nama_karyawan'],
        'nik' => ['nik'],
        'perusahaan' => ['perusahaan', 'nama_perusahaan'],
        'site' => ['site', 'site_dedicated'],
        'alasan' => ['alasan_banned', 'banned_reason', 'alasan'],
    ];

    /** @var array<string, string|null>|null peran → nama kolom; null = belum dibaca */
    private ?array $kolom = null;

    public function tersedia(): bool
    {
        $k = $this->kolom();

        return $k['sid'] !== null && $k['status'] !== null
            && ($k['mulai'] !== null || ($k['minggu'] !== null && $k['tahun'] !== null));
    }

    /**
     * Riwayat banned mingguan satu SID, terbaru dulu.
     *
     * @return list<array{mulai: CarbonImmutable, selesai: CarbonImmutable, nama: string, nik: string, perusahaan: string, site: string, alasan: string, status: string}>
     */
    public function riwayat(string $sid, int $maks): array
    {
        if (! $this->tersedia()) {
            return [];
        }
        $q = $this->dasar($sid);
        $k = $this->kolom();
        if ($k['mulai'] !== null) {
            $q->orderByDesc($k['mulai']);
        } else {
            $q->orderByDesc($k['tahun'])->orderByDesc($k['minggu']);
        }

        $hasil = [];
        foreach ($q->limit($maks)->get() as $r) {
            $baris = $this->normalkan($r);
            if ($baris !== null) {
                $hasil[] = $baris;
            }
        }

        return $hasil;
    }

    /**
     * Satu baris banned mingguan untuk minggu yang dimulai pada $mulai, dikunci untuk transaksi.
     *
     * @return array{mulai: CarbonImmutable, selesai: CarbonImmutable, nama: string, nik: string, perusahaan: string, site: string, alasan: string, status: string}|null
     */
    public function satuDikunci(string $sid, CarbonImmutable $mulai): ?array
    {
        if (! $this->tersedia()) {
            return null;
        }
        $q = $this->dasar($sid);
        $k = $this->kolom();
        if ($k['mulai'] !== null) {
            // Rentang 1 minggu: kolom "mulai" bisa saja tanggal di dalam minggu, bukan tepat Senin.
            $q->whereDate($k['mulai'], '>=', $mulai->toDateString())->whereDate($k['mulai'], '<=', $mulai->addDays(6)->toDateString());
        } else {
            $q->where($k['tahun'], $mulai->isoFormat('GGGG'))->where(fn (Builder $w) => $w
                ->where($k['minggu'], (int) $mulai->isoWeek())
                ->orWhere($k['minggu'], 'W'.$mulai->format('W'))
                ->orWhere($k['minggu'], (string) $mulai->isoWeek()));
        }

        $r = $q->lockForUpdate()->first();

        return $r === null ? null : $this->normalkan($r);
    }

    private function dasar(string $sid): Builder
    {
        $k = $this->kolom();

        return DB::table(self::TABEL)
            ->where($k['sid'], $sid)
            ->where($k['status'], 'like', 'BANNED%');
    }

    /**
     * @return array{mulai: CarbonImmutable, selesai: CarbonImmutable, nama: string, nik: string, perusahaan: string, site: string, alasan: string, status: string}|null
     */
    private function normalkan(object $r): ?array
    {
        $k = $this->kolom();
        $ambil = fn (string $peran): string => $k[$peran] !== null ? trim((string) ($r->{$k[$peran]} ?? '')) : '';

        try {
            if ($k['mulai'] !== null && $ambil('mulai') !== '') {
                $mulai = CarbonImmutable::parse($ambil('mulai'))->startOfWeek(CarbonImmutable::MONDAY);
            } else {
                $minggu = (int) preg_replace('/\D+/', '', $ambil('minggu'));
                $tahun = (int) preg_replace('/\D+/', '', $ambil('tahun'));
                if ($minggu < 1 || $minggu > 53 || $tahun < 2000) {
                    return null;
                }
                $mulai = CarbonImmutable::now()->setISODate($tahun, $minggu)->startOfDay();
            }
            $selesai = $k['selesai'] !== null && $ambil('selesai') !== ''
                ? CarbonImmutable::parse($ambil('selesai'))->startOfDay()
                : $mulai->addDays(6);
        } catch (Throwable) {
            return null;
        }

        return [
            'mulai' => $mulai, 'selesai' => $selesai, 'nama' => $ambil('nama'), 'nik' => $ambil('nik'),
            'perusahaan' => $ambil('perusahaan'), 'site' => $ambil('site'), 'alasan' => $ambil('alasan'), 'status' => $ambil('status'),
        ];
    }

    /** @return array<string, string|null> */
    private function kolom(): array
    {
        if ($this->kolom !== null) {
            return $this->kolom;
        }
        $ada = [];
        try {
            if (Schema::hasTable(self::TABEL)) {
                $ada = array_map('strtolower', Schema::getColumnListing(self::TABEL));
            }
        } catch (Throwable $e) {
            report($e);
        }
        $this->kolom = [];
        foreach (self::KANDIDAT as $peran => $calon) {
            $cocok = array_values(array_intersect($calon, $ada));
            $this->kolom[$peran] = $cocok[0] ?? null;
        }

        return $this->kolom;
    }
}
