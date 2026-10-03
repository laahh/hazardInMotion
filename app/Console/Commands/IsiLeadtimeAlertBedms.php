<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Mengisi lead_leadtime_alert_entry_to_bedms_month dari tabel alert mentah
 * di Postgres.
 *
 * Halaman /ohs-score-card/leadtime-alert-bedms membaca tabel rekap itu, bukan
 * tabel mentahnya, karena memindai bcsid.dms_alert setahun penuh terlalu berat
 * untuk dijalankan tiap kali halaman dibuka. Perintah ini yang menjembatani:
 * dijalankan terjadwal, hasilnya tinggal dibaca halaman.
 *
 * DIHITUNG PER BULAN, BUKAN SEKALI SETAHUN. Tiap bulan satu query terpisah
 * supaya rentang pemindaiannya tetap pendek dan kegagalan di satu bulan tidak
 * membatalkan bulan lain.
 *
 * NAMA KOLOM SUMBER BELUM PASTI. Query asalnya menandai sendiri beberapa kolom
 * sebagai asumsi, dan tabel Postgres-nya tidak bisa dijangkau dari lingkungan
 * pengembangan. Karena itu perintah ini memeriksa keberadaan tiap kolom lebih
 * dulu lewat information_schema dan berhenti dengan daftar kolom yang benar-
 * benar ada bila ada yang tidak cocok, bukan melempar galat SQL mentah.
 */
final class IsiLeadtimeAlertBedms extends Command
{
    protected $signature = 'ohs:isi-leadtime-alert
        {--tahun= : Tahun yang dihitung, bawaannya tahun berjalan}
        {--bulan= : Hanya bulan ini (1-12); bawaannya seluruh bulan sampai bulan berjalan}
        {--connection=pgsql_direct : Koneksi Postgres yang dipakai}
        {--dry-run : Hitung dan tampilkan hasilnya, tanpa menulis ke tabel rekap}
        {--cek-kolom : Hanya tampilkan daftar kolom tabel sumber, lalu berhenti}';

    protected $description = 'Menghitung % leadtime alert DMS masuk ke server BeDMS di bawah 5 menit, per site, perusahaan, dan bulan';

    private const TABEL_REKAP = 'lead_leadtime_alert_entry_to_bedms_month';

    private const SKEMA = 'bcsid';
    private const TABEL_ALERT = 'dms_alert';
    private const TABEL_EVIDENCE = 'dms_alert_evidence';

    /** Ambang evidence dianggap tepat waktu, dalam detik. */
    private const AMBANG_DETIK = 300;

    private const ZONA = 'Asia/Makassar';

    /**
     * Kolom yang dibutuhkan dari tiap tabel sumber, beserta calon namanya.
     *
     * Yang pertama cocok dengan skema tabel itulah yang dipakai, jadi
     * penamaan yang sedikit berbeda tidak langsung menggagalkan perintah ini.
     *
     * @var array<string, array<string, array<int, string>>>
     */
    private const KOLOM = [
        self::TABEL_ALERT => [
            'id' => ['id', 'alert_id'],
            'site' => ['site', 'site_name', 'nama_site'],
            'perusahaan' => ['perusahaan', 'company', 'nama_perusahaan', 'perusahaan_pic'],
            'waktu' => ['event_date', 'event_time', 'created_at', 'alert_time'],
        ],
        self::TABEL_EVIDENCE => [
            'alert_id' => ['alert_id', 'dms_alert_id', 'id_alert'],
            'waktu' => ['created_at', 'uploaded_at', 'received_at', 'server_time'],
        ],
    ];

    private const BULAN_INGGRIS = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];

    public function handle(): int
    {
        $koneksi = (string) $this->option('connection');
        $tahun = (int) ($this->option('tahun') ?: date('Y'));

        if ($this->option('cek-kolom')) {
            return $this->cekKolom($koneksi);
        }

        $kolom = $this->petakanKolom($koneksi);

        if ($kolom === null) {
            return self::FAILURE;
        }

        $bulanList = $this->bulanYangDihitung($tahun);

        if ($bulanList === []) {
            $this->warn('Tidak ada bulan untuk dihitung.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Menghitung tahun %d, bulan %s, lewat koneksi %s.',
            $tahun,
            implode(', ', $bulanList),
            $koneksi
        ));

        $semua = [];

        foreach ($bulanList as $bulan) {
            $this->line(sprintf('  %s ...', self::BULAN_INGGRIS[$bulan]));

            try {
                $baris = $this->hitungBulan($koneksi, $kolom, $tahun, $bulan);
            } catch (Throwable $e) {
                // Satu bulan gagal tidak membatalkan bulan lain; yang sudah
                // berhasil tetap ditulis di akhir.
                $this->error(sprintf('    gagal: %s', $e->getMessage()));
                continue;
            }

            $this->line(sprintf('    %d pasangan site-perusahaan', count($baris)));
            $semua = array_merge($semua, $baris);
        }

        if ($semua === []) {
            $this->warn('Tidak ada baris yang dihasilkan. Tabel rekap tidak diubah.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->tampilkan($semua);
            $this->info(sprintf('%d baris dihitung. Tidak ditulis karena --dry-run.', count($semua)));

            return self::SUCCESS;
        }

        $ditulis = $this->tulis($semua, $tahun, $bulanList);
        $this->info(sprintf('%d baris ditulis ke %s.', $ditulis, self::TABEL_REKAP));

        return self::SUCCESS;
    }

    /**
     * Menampilkan seluruh kolom tabel sumber apa adanya.
     *
     * Ada supaya nama kolom bisa diperiksa tanpa perlu menjalankan
     * perhitungannya lebih dulu, misalnya saat menyesuaikan konstanta KOLOM.
     */
    private function cekKolom(string $koneksi): int
    {
        foreach ([self::TABEL_ALERT, self::TABEL_EVIDENCE] as $tabel) {
            $this->newLine();
            $this->info(sprintf('%s.%s', self::SKEMA, $tabel));

            try {
                $kolom = DB::connection($koneksi)->select(
                    'SELECT column_name, data_type, is_nullable
                     FROM information_schema.columns
                     WHERE table_schema = ? AND table_name = ?
                     ORDER BY ordinal_position',
                    [self::SKEMA, $tabel]
                );
            } catch (Throwable $e) {
                $this->error('  ' . $e->getMessage());

                return self::FAILURE;
            }

            if ($kolom === []) {
                $this->warn('  tabel tidak ditemukan');
                continue;
            }

            $this->table(
                ['Kolom', 'Tipe', 'Boleh NULL'],
                array_map(static fn (object $k): array => [
                    $k->column_name, $k->data_type, $k->is_nullable,
                ], $kolom)
            );
        }

        $this->newLine();
        $this->line('Cocokkan dengan konstanta KOLOM di ' . static::class . '.');

        return self::SUCCESS;
    }

    /**
     * Memastikan tiap kolom yang dibutuhkan benar-benar ada, dan memilih nama
     * yang dipakai tabelnya.
     *
     * @return array<string, array<string, string>>|null  null bila ada yang tidak cocok
     */
    private function petakanKolom(string $koneksi): ?array
    {
        $out = [];
        $gagal = false;

        foreach (self::KOLOM as $tabel => $perlu) {
            try {
                $ada = DB::connection($koneksi)->select(
                    'SELECT column_name FROM information_schema.columns
                     WHERE table_schema = ? AND table_name = ?',
                    [self::SKEMA, $tabel]
                );
            } catch (Throwable $e) {
                $this->error(sprintf('Tidak bisa membaca skema %s.%s: %s', self::SKEMA, $tabel, $e->getMessage()));

                return null;
            }

            $daftar = array_map(static fn (object $r): string => $r->column_name, $ada);

            if ($daftar === []) {
                $this->error(sprintf('Tabel %s.%s tidak ditemukan.', self::SKEMA, $tabel));

                return null;
            }

            $indeks = array_change_key_case(array_combine($daftar, $daftar), CASE_LOWER);

            foreach ($perlu as $peran => $calon) {
                $ketemu = null;

                foreach ($calon as $nama) {
                    if (isset($indeks[mb_strtolower($nama)])) {
                        $ketemu = $indeks[mb_strtolower($nama)];
                        break;
                    }
                }

                if ($ketemu === null) {
                    $gagal = true;
                    $this->error(sprintf(
                        'Kolom untuk "%s" tidak ada di %s.%s. Dicari: %s.',
                        $peran,
                        self::SKEMA,
                        $tabel,
                        implode(', ', $calon)
                    ));
                    $this->line('    Kolom yang tersedia: ' . implode(', ', $daftar));
                    continue;
                }

                $out[$tabel][$peran] = $ketemu;
            }
        }

        if ($gagal) {
            $this->newLine();
            $this->error('Sesuaikan daftar calon nama kolom pada konstanta KOLOM di ' . static::class . '.');

            return null;
        }

        foreach ($out as $tabel => $peran) {
            $this->line(sprintf('  %s.%s -> %s', self::SKEMA, $tabel, json_encode($peran)));
        }

        return $out;
    }

    /** @return array<int, int> */
    private function bulanYangDihitung(int $tahun): array
    {
        $satu = $this->option('bulan');

        if ($satu !== null && $satu !== '') {
            $bulan = (int) $satu;

            return $bulan >= 1 && $bulan <= 12 ? [$bulan] : [];
        }

        // Tahun berjalan dihitung sampai bulan ini saja; tahun lampau penuh.
        $batas = $tahun === (int) date('Y') ? (int) date('n') : 12;

        return range(1, $batas);
    }

    /**
     * Satu bulan, satu query. Strukturnya mengikuti query asal: alert dalam
     * rentang bulan itu, evidence paling awal per alert, lalu dihitung berapa
     * persen yang selisihnya di bawah ambang.
     *
     * @param  array<string, array<string, string>>  $kolom
     * @return array<int, array<string, mixed>>
     */
    private function hitungBulan(string $koneksi, array $kolom, int $tahun, int $bulan): array
    {
        $a = $kolom[self::TABEL_ALERT];
        $e = $kolom[self::TABEL_EVIDENCE];

        $mulai = sprintf('%04d-%02d-01', $tahun, $bulan);
        $akhir = $bulan === 12
            ? sprintf('%04d-01-01', $tahun + 1)
            : sprintf('%04d-%02d-01', $tahun, $bulan + 1);

        $sql = sprintf(
            'WITH alert AS (
                 SELECT a.%1$s AS alert_id,
                        a.%2$s AS site,
                        a.%3$s AS perusahaan,
                        a.%4$s AT TIME ZONE ? AS event_time
                 FROM %5$s.%6$s a
                 WHERE a.%4$s >= ?::date AND a.%4$s < ?::date
             ),
             evidence AS (
                 SELECT ev.%7$s AS alert_id,
                        MIN(ev.%8$s AT TIME ZONE ?) AS evidence_time
                 FROM %5$s.%9$s ev
                 WHERE ev.%7$s IN (SELECT alert_id FROM alert)
                 GROUP BY ev.%7$s
             )
             SELECT a.site,
                    a.perusahaan,
                    COUNT(*) AS total_alert,
                    COUNT(ev.alert_id) AS ada_evidence,
                    SUM(CASE WHEN EXTRACT(EPOCH FROM (ev.evidence_time - a.event_time)) <= ?
                             THEN 1 ELSE 0 END) AS tepat_waktu
             FROM alert a
             LEFT JOIN evidence ev ON ev.alert_id = a.alert_id
             GROUP BY a.site, a.perusahaan
             ORDER BY a.site, a.perusahaan',
            $a['id'], $a['site'], $a['perusahaan'], $a['waktu'],
            self::SKEMA, self::TABEL_ALERT,
            $e['alert_id'], $e['waktu'], self::TABEL_EVIDENCE
        );

        $rows = DB::connection($koneksi)->select(
            $sql,
            [self::ZONA, $mulai, $akhir, self::ZONA, self::AMBANG_DETIK]
        );

        $out = [];

        foreach ($rows as $row) {
            $total = (int) $row->total_alert;

            if ($total === 0) {
                continue;
            }

            $out[] = [
                'site' => trim((string) $row->site),
                'perusahaan' => trim((string) $row->perusahaan),
                'bulan' => self::BULAN_INGGRIS[$bulan],
                // Persen, bukan pecahan; lihat catatan skala di controller.
                'persen' => round((int) $row->tepat_waktu / $total * 100, 2),
                'total_alert' => $total,
                'ada_evidence' => (int) $row->ada_evidence,
            ];
        }

        return $out;
    }

    /** @param  array<int, array<string, mixed>>  $baris */
    private function tampilkan(array $baris): void
    {
        $this->table(
            ['Site', 'Perusahaan', 'Bulan', '% <5 menit', 'Alert', 'Ada evidence'],
            array_map(static fn (array $r): array => [
                $r['site'], $r['perusahaan'], $r['bulan'],
                $r['persen'], $r['total_alert'], $r['ada_evidence'],
            ], array_slice($baris, 0, 40))
        );

        if (count($baris) > 40) {
            $this->line(sprintf('  ... dan %d baris lagi', count($baris) - 40));
        }
    }

    /**
     * Menulis hasil ke tabel rekap.
     *
     * Baris lama untuk tahun & bulan yang sama dihapus dulu supaya menjalankan
     * ulang perintah ini tidak menumpuk duplikat.
     *
     * @param  array<int, array<string, mixed>>  $baris
     * @param  array<int, int>  $bulanList
     */
    private function tulis(array $baris, int $tahun, array $bulanList): int
    {
        $namaBulan = array_map(static fn (int $b): string => self::BULAN_INGGRIS[$b], $bulanList);
        $sekarang = now();

        return DB::transaction(function () use ($baris, $namaBulan, $sekarang): int {
            DB::table(self::TABEL_REKAP)->whereIn('Month_of_event_time', $namaBulan)->delete();

            $muatan = array_map(static fn (array $r): array => [
                'scraped_at' => $sekarang,
                'tableau_view_id' => null,
                'source_url' => 'artisan ohs:isi-leadtime-alert',
                'Month_of_event_time' => $r['bulan'],
                'Perusahaan' => $r['perusahaan'],
                'site' => $r['site'],
                'Leadtime_Alert_masuk_ke_Server_Evidence_BeDMS_under_5_min' => $r['persen'],
            ], $baris);

            foreach (array_chunk($muatan, 500) as $potongan) {
                DB::table(self::TABEL_REKAP)->insert($potongan);
            }

            return count($muatan);
        });
    }
}
