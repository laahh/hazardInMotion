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
 * UKURANNYA: dari sekian alert, berapa persen yang evidence-nya sampai di
 * server DMS dalam AMBANG_DETIK sejak kejadiannya.
 *
 * CUKUP SATU TABEL. Rancangan awal menjoin dms_alert_evidence untuk mencari
 * waktu evidence paling awal, tetapi dms_alert sendiri sudah menyimpan
 * dms_server_evidence_ingestion_time, yaitu persis waktu evidence masuk server.
 * Tanpa join, pemindaiannya jauh lebih ringan dan maknanya lebih lurus.
 * Alert yang kolom itunya NULL berarti evidence-nya tidak pernah sampai, jadi
 * dihitung sebagai tidak tepat waktu, bukan dikeluarkan dari penyebut.
 *
 * TIDAK ADA KONVERSI ZONA WAKTU. Kedua kolom bertipe timestamp without time
 * zone dan yang dihitung adalah selisih keduanya, sehingga pergeseran zona
 * akan saling meniadakan. Menambahkan AT TIME ZONE hanya menambah risiko salah
 * tafsir tanpa mengubah hasil.
 *
 * SITE & PERUSAHAAN BERUPA ID. dms_alert menyimpan mine_operation_id dan
 * contractor_id, bukan nama seperti "BMO 1" atau "PT BAR". Nama dicari lewat
 * tabel rujukan yang ditemukan saat jalan (lihat cariRujukan); bila tabelnya
 * tidak ketemu, id-nya dipakai apa adanya agar perintah tetap menghasilkan
 * sesuatu, dan itu diberitahukan.
 *
 * NAMA KOLOM DIPETAKAN SAAT JALAN, bukan ditulis mati, supaya perbedaan
 * penamaan antar lingkungan tidak langsung menggagalkan perintah ini.
 * `--cek-kolom` menampilkan isi skema sebenarnya.
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

    /** Ambang evidence dianggap tepat waktu, dalam detik. */
    private const AMBANG_DETIK = 300;

    /**
     * Kolom yang dibutuhkan dari dms_alert, beserta calon namanya.
     *
     * Yang pertama cocok dengan skema tabel itulah yang dipakai.
     *
     * @var array<string, array<int, string>>
     */
    private const KOLOM = [
        'site' => ['mine_operation_id'],
        'perusahaan' => ['contractor_id'],
        'waktu_event' => ['event_time'],
        'waktu_evidence' => ['dms_server_evidence_ingestion_time'],
    ];

    /** Kolom yang dipakai kalau ada, tetapi tidak wajib. */
    private const KOLOM_OPSIONAL = [
        'dihapus' => ['deleted_at'],
    ];

    /**
     * Tabel rujukan untuk menerjemahkan id menjadi nama.
     *
     * @var array<string, array{tabel: array<int, string>, id: array<int, string>, nama: array<int, string>}>
     */
    private const RUJUKAN = [
        'site' => [
            'tabel' => ['mine_operation', 'mine_operations', 'm_mine_operation'],
            'id' => ['id', 'origin_id', 'mine_operation_id'],
            'nama' => ['name', 'mine_operation_name', 'nama', 'alias', 'code'],
        ],
        'perusahaan' => [
            'tabel' => ['contractor', 'contractors', 'm_contractor'],
            'id' => ['id', 'origin_id', 'contractor_id'],
            'nama' => ['alias', 'short_name', 'name', 'contractor_name', 'nama', 'code'],
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

        $semua = $this->terjemahkanNama($koneksi, $semua);

        if ($this->option('dry-run')) {
            $this->tampilkan($semua);
            $this->info(sprintf('%d baris dihitung. Tidak ditulis karena --dry-run.', count($semua)));

            return self::SUCCESS;
        }

        $ditulis = $this->tulis($semua, $bulanList);
        $this->info(sprintf('%d baris ditulis ke %s.', $ditulis, self::TABEL_REKAP));

        return self::SUCCESS;
    }

    /**
     * Menampilkan seluruh kolom tabel sumber apa adanya, beserta tabel rujukan
     * yang tersedia.
     *
     * Ada supaya nama kolom bisa diperiksa tanpa perlu menjalankan
     * perhitungannya lebih dulu.
     */
    private function cekKolom(string $koneksi): int
    {
        foreach ([self::TABEL_ALERT, 'dms_alert_evidence'] as $tabel) {
            $this->newLine();
            $this->info(sprintf('%s.%s', self::SKEMA, $tabel));

            try {
                $kolom = $this->kolomTabel($koneksi, $tabel);
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
        $this->info('Tabel rujukan yang mungkin memuat nama site & perusahaan:');

        try {
            $tabel = DB::connection($koneksi)->select(
                "SELECT table_name FROM information_schema.tables
                 WHERE table_schema = ?
                   AND (table_name ILIKE '%mine_operation%' OR table_name ILIKE '%contractor%')
                 ORDER BY table_name",
                [self::SKEMA]
            );
        } catch (Throwable $e) {
            $this->error('  ' . $e->getMessage());

            return self::FAILURE;
        }

        if ($tabel === []) {
            $this->warn('  tidak ada; id akan dipakai apa adanya sebagai nama');
        }

        foreach ($tabel as $t) {
            $kolom = $this->kolomTabel($koneksi, $t->table_name);
            $this->line(sprintf(
                '  %s.%s (%s)',
                self::SKEMA,
                $t->table_name,
                implode(', ', array_map(static fn (object $k): string => $k->column_name, $kolom))
            ));
        }

        $this->newLine();
        $this->line('Cocokkan dengan konstanta KOLOM dan RUJUKAN di ' . static::class . '.');

        return self::SUCCESS;
    }

    /** @return array<int, object> */
    private function kolomTabel(string $koneksi, string $tabel): array
    {
        return DB::connection($koneksi)->select(
            'SELECT column_name, data_type, is_nullable
             FROM information_schema.columns
             WHERE table_schema = ? AND table_name = ?
             ORDER BY ordinal_position',
            [self::SKEMA, $tabel]
        );
    }

    /**
     * Memastikan tiap kolom yang dibutuhkan benar-benar ada, dan memilih nama
     * yang dipakai tabelnya.
     *
     * @return array<string, string>|null  null bila ada yang tidak cocok
     */
    private function petakanKolom(string $koneksi): ?array
    {
        try {
            $ada = $this->kolomTabel($koneksi, self::TABEL_ALERT);
        } catch (Throwable $e) {
            $this->error(sprintf('Tidak bisa membaca skema %s.%s: %s', self::SKEMA, self::TABEL_ALERT, $e->getMessage()));

            return null;
        }

        $daftar = array_map(static fn (object $r): string => $r->column_name, $ada);

        if ($daftar === []) {
            $this->error(sprintf('Tabel %s.%s tidak ditemukan.', self::SKEMA, self::TABEL_ALERT));

            return null;
        }

        $indeks = [];

        foreach ($daftar as $nama) {
            $indeks[mb_strtolower($nama)] = $nama;
        }

        $out = [];
        $gagal = false;

        foreach (self::KOLOM as $peran => $calon) {
            $ketemu = $this->pilihKolom($indeks, $calon);

            if ($ketemu === null) {
                $gagal = true;
                $this->error(sprintf(
                    'Kolom untuk "%s" tidak ada di %s.%s. Dicari: %s.',
                    $peran,
                    self::SKEMA,
                    self::TABEL_ALERT,
                    implode(', ', $calon)
                ));
                continue;
            }

            $out[$peran] = $ketemu;
        }

        if ($gagal) {
            $this->newLine();
            $this->line('Jalankan dengan --cek-kolom untuk melihat kolom yang tersedia, lalu sesuaikan');
            $this->line('konstanta KOLOM di ' . static::class . '.');

            return null;
        }

        foreach (self::KOLOM_OPSIONAL as $peran => $calon) {
            $ketemu = $this->pilihKolom($indeks, $calon);

            if ($ketemu !== null) {
                $out[$peran] = $ketemu;
            }
        }

        $this->line('  kolom: ' . json_encode($out));

        if (! isset($out['dihapus'])) {
            $this->warn('  tanpa kolom deleted_at: alert yang sudah dihapus ikut terhitung');
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $indeks
     * @param  array<int, string>  $calon
     */
    private function pilihKolom(array $indeks, array $calon): ?string
    {
        foreach ($calon as $nama) {
            if (isset($indeks[mb_strtolower($nama)])) {
                return $indeks[mb_strtolower($nama)];
            }
        }

        return null;
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
     * Satu bulan, satu query, satu tabel.
     *
     * @param  array<string, string>  $kolom
     * @return array<int, array<string, mixed>>
     */
    private function hitungBulan(string $koneksi, array $kolom, int $tahun, int $bulan): array
    {
        $mulai = sprintf('%04d-%02d-01', $tahun, $bulan);
        $akhir = $bulan === 12
            ? sprintf('%04d-01-01', $tahun + 1)
            : sprintf('%04d-%02d-01', $tahun, $bulan + 1);

        $filterHapus = isset($kolom['dihapus'])
            ? sprintf('AND %s IS NULL', $kolom['dihapus'])
            : '';

        $sql = sprintf(
            'SELECT %1$s AS site_id,
                    %2$s AS perusahaan_id,
                    COUNT(*) AS total_alert,
                    COUNT(%4$s) AS ada_evidence,
                    SUM(CASE WHEN %4$s IS NOT NULL
                              AND EXTRACT(EPOCH FROM (%4$s - %3$s)) <= ?
                             THEN 1 ELSE 0 END) AS tepat_waktu
             FROM %5$s.%6$s
             WHERE %3$s >= ?::timestamp AND %3$s < ?::timestamp
             %7$s
             GROUP BY 1, 2
             ORDER BY 1, 2',
            $kolom['site'],
            $kolom['perusahaan'],
            $kolom['waktu_event'],
            $kolom['waktu_evidence'],
            self::SKEMA,
            self::TABEL_ALERT,
            $filterHapus
        );

        $rows = DB::connection($koneksi)->select($sql, [self::AMBANG_DETIK, $mulai, $akhir]);

        $out = [];

        foreach ($rows as $row) {
            $total = (int) $row->total_alert;

            if ($total === 0) {
                continue;
            }

            $out[] = [
                'site_id' => (string) $row->site_id,
                'perusahaan_id' => (string) $row->perusahaan_id,
                'bulan' => self::BULAN_INGGRIS[$bulan],
                // Persen, bukan pecahan; lihat catatan skala di controller.
                'persen' => round((int) $row->tepat_waktu / $total * 100, 2),
                'total_alert' => $total,
                'ada_evidence' => (int) $row->ada_evidence,
            ];
        }

        return $out;
    }

    /**
     * Mengganti id site & perusahaan dengan namanya, bila tabel rujukannya ada.
     *
     * @param  array<int, array<string, mixed>>  $baris
     * @return array<int, array<string, mixed>>
     */
    private function terjemahkanNama(string $koneksi, array $baris): array
    {
        foreach (['site', 'perusahaan'] as $peran) {
            $kunci = $peran . '_id';
            $idList = array_values(array_unique(array_column($baris, $kunci)));
            $peta = $this->petaNama($koneksi, $peran, $idList);

            foreach ($baris as $i => $row) {
                // Tanpa rujukan, id dipakai apa adanya: lebih baik tabel rekap
                // berisi id yang bisa ditelusuri daripada tidak terisi.
                $baris[$i][$peran] = $peta[$row[$kunci]] ?? $row[$kunci];
            }
        }

        return $baris;
    }

    /**
     * @param  array<int, string>  $idList
     * @return array<string, string>
     */
    private function petaNama(string $koneksi, string $peran, array $idList): array
    {
        if ($idList === []) {
            return [];
        }

        $rujukan = $this->cariRujukan($koneksi, $peran);

        if ($rujukan === null) {
            $this->warn(sprintf('  tabel rujukan %s tidak ditemukan; id dipakai sebagai nama', $peran));

            return [];
        }

        try {
            $rows = DB::connection($koneksi)
                ->table(self::SKEMA . '.' . $rujukan['tabel'])
                ->whereIn($rujukan['id'], $idList)
                ->get([$rujukan['id'] . ' AS id', $rujukan['nama'] . ' AS nama']);
        } catch (Throwable $e) {
            $this->warn(sprintf('  gagal membaca rujukan %s: %s', $peran, $e->getMessage()));

            return [];
        }

        $this->line(sprintf(
            '  rujukan %s: %s.%s (%s -> %s), %d nama ditemukan',
            $peran,
            self::SKEMA,
            $rujukan['tabel'],
            $rujukan['id'],
            $rujukan['nama'],
            $rows->count()
        ));

        $peta = [];

        foreach ($rows as $row) {
            if ($row->nama !== null && trim((string) $row->nama) !== '') {
                $peta[(string) $row->id] = trim((string) $row->nama);
            }
        }

        return $peta;
    }

    /**
     * Mencari tabel rujukan beserta kolom id & namanya.
     *
     * @return array{tabel: string, id: string, nama: string}|null
     */
    private function cariRujukan(string $koneksi, string $peran): ?array
    {
        $calon = self::RUJUKAN[$peran];

        foreach ($calon['tabel'] as $tabel) {
            try {
                $kolom = $this->kolomTabel($koneksi, $tabel);
            } catch (Throwable) {
                continue;
            }

            if ($kolom === []) {
                continue;
            }

            $indeks = [];

            foreach ($kolom as $k) {
                $indeks[mb_strtolower($k->column_name)] = $k->column_name;
            }

            $id = $this->pilihKolom($indeks, $calon['id']);
            $nama = $this->pilihKolom($indeks, $calon['nama']);

            if ($id !== null && $nama !== null) {
                return ['tabel' => $tabel, 'id' => $id, 'nama' => $nama];
            }
        }

        return null;
    }

    /** @param  array<int, array<string, mixed>>  $baris */
    private function tampilkan(array $baris): void
    {
        $this->table(
            ['Site', 'Perusahaan', 'Bulan', '% tepat waktu', 'Alert', 'Ada evidence'],
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
     * Baris lama untuk bulan yang sama dihapus dulu supaya menjalankan ulang
     * perintah ini tidak menumpuk duplikat.
     *
     * @param  array<int, array<string, mixed>>  $baris
     * @param  array<int, int>  $bulanList
     */
    private function tulis(array $baris, array $bulanList): int
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
