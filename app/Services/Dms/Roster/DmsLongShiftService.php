<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Long Shift pengawas (docs/overshift.md) untuk panel "Long Shift" di kartu
 * Scorecard Ringkasan Roster.
 *
 * Deteksinya dikerjakan Postgres (DmsLongShiftReader); service ini merakit
 * baseline pengawas + hasil deteksi menjadi satu baris per pengawas dengan
 * status, lalu menyimpannya di cache. Pola sumbernya sama dengan
 * DmsRosterRegLiveService: command terjadwal dms:sync-roster-rfid (Tahap 7)
 * memanggil segarkan(), halaman cukup ambil() — request web di server tidak
 * bisa terhubung ke OLAP.
 *
 * Status per pengawas (dokumen menilai per shift; di sini diringkas per orang):
 *   LONG_SHIFT            minimal satu shift berlanjut ke shift berikutnya
 *   NORMAL                ada shift yang bisa dinilai, tidak ada yang berlanjut
 *   DATA_ANOMALY          ada tap tapi tidak ada shift yang bisa dinilai, dan ada anomali
 *                         (IN tanpa OUT, OUT tanpa IN, pasangan terlalu panjang)
 *   TIDAK_TERKLASIFIKASI  ada tap, tanpa anomali, tapi tidak ada blok >= min_shift_menit
 *   NO_DATA               tidak ada tap RFID sama sekali di jendela
 * Anomali tetap dihitung untuk semua status — banyak pos hanya mencatat IN,
 * jadi menjadikannya status utama akan menutupi hasil yang sebenarnya bisa dinilai.
 */
final class DmsLongShiftService
{
    public const STATUS = ['LONG_SHIFT', 'NORMAL', 'DATA_ANOMALY', 'TIDAK_TERKLASIFIKASI', 'NO_DATA'];

    private const KUNCI_SEGAR = 'dms_roster:long_shift:segar:v1';

    private const KUNCI_TERAKHIR = 'dms_roster:long_shift:terakhir:v1';

    private const KUNCI_GAGAL = 'dms_roster:long_shift:gagal:v1';

    private ?string $galat = null;

    public function __construct(
        private readonly DmsLongShiftReader $reader,
    ) {}

    /**
     * Hasil untuk halaman: cache segar → hitung langsung → hasil terakhir
     * yang berhasil (ditandai 'basi'). null hanya bila belum pernah berhasil.
     *
     * @return array<string, mixed>|null
     */
    public function ambil(): ?array
    {
        $tersimpan = Cache::get(self::KUNCI_SEGAR);
        if (is_array($tersimpan)) {
            return $tersimpan;
        }

        $gagal = Cache::get(self::KUNCI_GAGAL);
        if (is_string($gagal)) {
            $this->galat = $gagal;

            return $this->cadangan();
        }

        return $this->segarkan() ?? $this->cadangan();
    }

    /**
     * Hitung ulang dari OLAP sekarang juga dan simpan.
     *
     * @return array<string, mixed>|null
     */
    public function segarkan(): ?array
    {
        $sekarang = CarbonImmutable::now()->startOfMinute();
        $dari = $sekarang->startOfDay()->subDays(max(1, (int) config('dms_roster.long_shift.jendela_hari', 7)));

        try {
            $mulai = microtime(true);
            $pengawas = $this->reader->pengawas();
            $deteksi = $this->reader->deteksi($dari, $sekarang);
            $detik = microtime(true) - $mulai;
        } catch (Throwable $e) {
            $this->galat = $e->getMessage();
            Log::warning('DmsLongShift gagal: '.$e->getMessage());
            Cache::put(self::KUNCI_GAGAL, $this->galat, 300);

            return null;
        }

        $baris = $this->gabung($pengawas, $deteksi);
        $hasil = [
            'basi' => false,
            'dari' => $dari->toDateString(),
            'sampai' => $sekarang->toDateTimeString(),
            'diambil' => CarbonImmutable::now()->toDateTimeString(),
            'detik' => round($detik, 1),
            'parameter' => $this->parameter(),
            'ringkas' => $this->ringkas($baris),
            'baris' => $baris,
        ];

        Cache::put(self::KUNCI_SEGAR, $hasil, (int) config('dms_roster.long_shift.cache_ttl', 4500));
        Cache::put(self::KUNCI_TERAKHIR, $hasil, now()->addDays(7));
        Cache::forget(self::KUNCI_GAGAL);

        return $hasil;
    }

    public function galatTerakhir(): ?string
    {
        return $this->galat;
    }

    /**
     * Baseline pengawas LEFT JOIN hasil deteksi → satu baris per pengawas,
     * urut: LONG_SHIFT terbanyak lebih dulu, lalu status, lalu nama. Murni.
     *
     * @param  list<object>  $pengawas  Hasil DmsLongShiftReader::pengawas().
     * @param  list<object>  $deteksi  Hasil DmsLongShiftReader::deteksi().
     * @return list<array<string, mixed>>
     */
    public function gabung(array $pengawas, array $deteksi): array
    {
        $perSid = [];
        foreach ($deteksi as $d) {
            $perSid[(string) $d->sid] = $d;
        }

        $baris = [];
        foreach ($pengawas as $p) {
            $sid = (string) $p->sid;
            $d = $perSid[$sid] ?? null;
            $r = [
                'sid' => $sid,
                'nama' => trim((string) $p->nama),
                'perusahaan' => trim((string) ($p->perusahaan ?? '')),
                'jabatan' => trim((string) ($p->jabatan ?? '')),
                'departemen' => trim((string) ($p->departemen ?? '')),
                'ada_tap' => $d !== null,
                'n_shift' => (int) ($d->n_shift ?? 0),
                'n_long' => (int) ($d->n_long ?? 0),
                'n_anomali' => (int) ($d->n_anomali ?? 0),
                'long_shift' => $this->json($d->long_shift ?? null),
                'anomali' => $this->json($d->anomali ?? null),
            ];
            $r['status'] = self::status($r);
            $baris[] = $r;
        }

        $urut = array_flip(self::STATUS);
        usort($baris, static fn (array $a, array $b): int => [$urut[$a['status']], -$a['n_long'], $a['nama']]
            <=> [$urut[$b['status']], -$b['n_long'], $b['nama']]);

        return $baris;
    }

    /**
     * @param  array{ada_tap:bool,n_shift:int,n_long:int,n_anomali:int}  $r
     */
    public static function status(array $r): string
    {
        return match (true) {
            $r['n_long'] > 0 => 'LONG_SHIFT',
            $r['n_shift'] > 0 => 'NORMAL',
            ! $r['ada_tap'] => 'NO_DATA',
            $r['n_anomali'] > 0 => 'DATA_ANOMALY',
            default => 'TIDAK_TERKLASIFIKASI',
        };
    }

    /**
     * Saring + paginasi untuk endpoint JSON.
     *
     * @param  list<array<string, mixed>>  $baris
     * @return array{baris: list<array<string, mixed>>, total: int, halaman: int, total_halaman: int}
     */
    public function saring(array $baris, string $status, string $q, int $halaman, int $perHalaman): array
    {
        $q = mb_strtolower(trim($q));
        $hasil = array_values(array_filter($baris, static function (array $r) use ($status, $q): bool {
            if ($status !== '' && $r['status'] !== $status) {
                return false;
            }
            if ($q === '') {
                return true;
            }

            return str_contains(mb_strtolower($r['nama'].' '.$r['sid'].' '.$r['perusahaan'].' '.$r['jabatan'].' '.$r['departemen']), $q);
        }));

        $total = count($hasil);
        $totalHalaman = max(1, (int) ceil($total / max(1, $perHalaman)));
        $halaman = min(max(1, $halaman), $totalHalaman);

        return [
            'baris' => array_slice($hasil, ($halaman - 1) * $perHalaman, $perHalaman),
            'total' => $total,
            'halaman' => $halaman,
            'total_halaman' => $totalHalaman,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @return array<string, int>
     */
    private function ringkas(array $baris): array
    {
        $out = array_fill_keys(self::STATUS, 0) + ['pengawas' => count($baris), 'ada_tap' => 0, 'kejadian_long' => 0, 'anomali' => 0];
        foreach ($baris as $r) {
            $out[$r['status']]++;
            $out['ada_tap'] += $r['ada_tap'] ? 1 : 0;
            $out['kejadian_long'] += $r['n_long'];
            $out['anomali'] += $r['n_anomali'];
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    private function parameter(): array
    {
        $c = config('dms_roster.long_shift', []);

        return [
            'jendela_hari' => (int) ($c['jendela_hari'] ?? 7),
            'shift_mulai_jam' => (int) ($c['shift_mulai_jam'] ?? 7),
            'duplikat_menit' => (int) ($c['duplikat_menit'] ?? 2),
            'jeda_maks_menit' => (int) ($c['jeda_maks_menit'] ?? 60),
            'pasangan_maks_jam' => (int) ($c['pasangan_maks_jam'] ?? 36),
            'min_shift_menit' => (int) ($c['min_shift_menit'] ?? 360),
            'min_lanjut_menit' => (int) ($c['min_lanjut_menit'] ?? 30),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function json(mixed $nilai): array
    {
        if (! is_string($nilai) || $nilai === '') {
            return [];
        }
        $d = json_decode($nilai, true);

        return is_array($d) ? array_values($d) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cadangan(): ?array
    {
        $terakhir = Cache::get(self::KUNCI_TERAKHIR);

        return is_array($terakhir) ? ['basi' => true, 'galat' => $this->galat] + $terakhir : null;
    }
}
