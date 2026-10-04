<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsScoreCard;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Dashboard "Incident Management & IPLS".
 *
 * Sumbernya SATU materialized view di OBDS, bcbeats.mv_investigasi pada
 * database hse_automation: 2.286 insiden beserta dua kolom JSONB,
 * rootcause (analisis 5 layer IPLS) dan tindakan_perbaikan (CAR).
 *
 * KONEKSINYA pgsql_direct, mengikuti BerecordController di modul yang sama:
 * tunnel SSH di server tidak selalu hidup, akses langsung ke RDS lebih andal.
 * Dari jaringan lokal RDS memang tidak terjangkau, karena itu seluruh kueri
 * dibungkus try/catch dan halaman tetap terender dengan pesan yang jelas
 * alih-alih melempar 500. Lihat data().
 *
 * SEMUA AGREGASI DIKERJAKAN DI SQL, lalu dikirim sekali sebagai empat
 * kumpulan angka yang sudah ringkas; penyaringan tahun, site dan status layer
 * dikerjakan di browser. Pilihan ini disengaja: keempatnya hanya ±6.500 baris
 * ringkasan, dan memfilter di browser membuat mengganti filter tidak perlu
 * memukul RDS berulang kali untuk data yang isinya sama.
 *
 * EMPAT KUMPULAN ITU, dan kenapa grain-nya berbeda-beda:
 *
 *   d1  insiden   per bulan x site x jenis x kategori x status investigasi
 *   d2  temuan    per tahun x site x kategori x aktivitas x status layer
 *   car tindakan  per tahun x site x layer perbaikan x status x overdue
 *   rc  pasangan  per tahun x site x layer root cause x layer CAR
 *
 * d1 bergrain bulan karena dipakai grafik tren; tiga sisanya cukup per tahun
 * dan memakai grain bulan hanya akan melipatgandakan barisnya tanpa ada panel
 * yang membutuhkannya.
 *
 * SATU INSIDEN BISA PUNYA BANYAK TEMUAN. d2, car dan rc mencacah elemen di
 * dalam JSONB, bukan insiden, jadi jumlahnya memang jauh melebihi cacah
 * insiden di d1 dan keduanya tidak boleh dibandingkan langsung.
 *
 * YANG DIKECUALIKAN: status_investigasi 'DELETED' dan data uji
 * (kategori_kecelakaan 'Test'), sejalan catatan di kaki halaman.
 */
final class IncidentManagementDashboardController extends Controller
{
    /** Lihat catatan koneksi di docblock kelas. */
    private const CONNECTION = 'pgsql_direct';

    private const TABLE = 'bcbeats.mv_investigasi';

    /**
     * mv_investigasi adalah snapshot materialized view, bukan data realtime,
     * jadi menahan hasilnya sepuluh menit tidak membuat angkanya basi tetapi
     * menghemat empat kueri JSONB yang lumayan berat ke RDS.
     */
    private const CACHE_TTL = 600;

    private const CACHE_KEY = 'ohs-score-card.incident-ipls.v1';

    /**
     * Batas bawah data yang ditarik. Di bawah 2022 hanya tersisa satu baris
     * tahun 2021 yang jelas salah input, dan memasukkannya hanya menambah satu
     * pilihan tahun yang isinya nyaris kosong.
     */
    private const TAHUN_AWAL = 2022;

    /** Insiden dianggap punya analisis IPLS bila rootcause-nya tidak kosong. */
    private const SQL_ADA_IPLS = "(jsonb_typeof(rootcause) = 'array' AND jsonb_array_length(rootcause) > 0)";

    /** Tanggal & site memakai hasil validasi investigasi, jatuh ke data CCR bila kosong. */
    private const SQL_TANGGAL = 'COALESCE(tanggal_kejadian, ccr_tanggal_insiden)';

    private const SQL_SITE = "COALESCE(NULLIF(TRIM(site), ''), NULLIF(TRIM(ccr_site), ''), 'Tidak diketahui')";

    /**
     * Penggabungan kategori kecelakaan, sejalan catatan di kaki halaman:
     * tiga rasa Near Miss jadi satu, tiga tingkat cedera jadi Injury, dan
     * Hazard HIPO digabung dengan Pelanggaran PSPP.
     */
    private const SQL_KATEGORI = <<<'SQL'
        CASE
            WHEN kategori_kecelakaan IN ('Near Miss', 'Near Miss - HIPO', 'Near Miss - Non HIPO') THEN 'Near Miss'
            WHEN kategori_kecelakaan IN ('First Aid', 'Medical Treatment Injury', 'Major Injury') THEN 'Injury'
            WHEN kategori_kecelakaan IN ('Hazard HIPO', 'Pelanggaran PSPP') THEN 'Hazard HIPO / PSPP'
            WHEN COALESCE(TRIM(kategori_kecelakaan), '') = '' THEN 'Belum dikategorikan'
            ELSE kategori_kecelakaan
        END
        SQL;

    /** Nomor layer dari teks "Layer 3"; 0 bila kosong atau tak terbaca. */
    private const SQL_LAYER = "COALESCE(NULLIF(regexp_replace(%s, '\\D', '', 'g'), '')::int, 0)";

    private const LABEL_STATUS = [
        'INSIDEN BARU' => 'Insiden baru',
        'INVESTIGASI' => 'Investigasi',
        'TIDAK INVESTIGASI' => 'Tidak investigasi',
    ];

    private const LABEL_STATUS_LAYER = [
        'ROOT CAUSE' => 'Root cause',
        'NON CONFIRMITY' => 'Non-conformity',
        'IMPROVEMENT' => 'Improvement',
    ];

    private const LABEL_STATUS_CAR = [
        'CLOSED' => 'Closed',
        'CLOSED OVERDUE' => 'Closed overdue',
        'OPEN' => 'Open',
        'PENDING APPROVAL' => 'Pending approval',
        'REJECT' => 'Reject',
    ];

    /** Nama layer disusun dari daftar aktivitas di dalamnya, bukan nama resmi sistem. */
    private const NAMA_LAYER = [
        1 => 'Layer 1 · Sistem & Kebijakan',
        2 => 'Layer 2 · Perencanaan & Program',
        3 => 'Layer 3 · Pelaksanaan Lapangan',
        4 => 'Layer 4 · Kontrol Teknologi',
        5 => 'Layer 5 · Pengaman Fisik & Darurat',
    ];

    private const NAMA_LAYER_PENDEK = [
        0 => 'Tanpa layer',
        1 => 'L1 Sistem',
        2 => 'L2 Perencanaan',
        3 => 'L3 Pelaksanaan',
        4 => 'L4 Teknologi',
        5 => 'L5 Pengaman',
    ];

    /**
     * Nama aktivitas yang terlalu panjang untuk label Sankey, dipendekkan.
     * Hanya yang benar-benar melebar; sisanya dipakai apa adanya.
     */
    private const AKTIVITAS_PENDEK = [
        'Pembelian dan Penanganan Material (Material handling)' => 'Material handling',
        'Guarding/cover benda berputar dan titik jepit' => 'Guarding & titik jepit',
        'Pemenuhan rambu/Safety Sign/IMO Sign' => 'Rambu / Safety sign',
        'SOP (Policy, Procedure, IK, Std & form)' => 'SOP (Policy, IK, standar)',
        'Rencana kerja harian/Daily Maintenance' => 'Rencana kerja harian',
        'Pengecekan tongkang before after loading' => 'Cek tongkang',
        'Personal Permit (ID/ SIMPER/ KIMPER)' => 'Personal permit (SIMPER/KIMPER)',
        'Pelaksanaan pekerjaan sesuai SOP' => 'Pelaksanaan kerja sesuai SOP',
        'Pengawasan pekerjaan oleh pengawas' => 'Pengawasan oleh pengawas',
        'Organizational Structure & Leadership' => 'Struktur organisasi & leadership',
        'Safety Accountability Program (SAP)' => 'SAP (Safety Accountability Program)',
        'Rencana kerja (weekly-up plan)' => 'Rencana kerja mingguan',
        'P2H (incl. emergency equipment)' => 'P2H',
        'GPS (Posisi dan Kecepatan)' => 'GPS',
        'Recruitment (incl. psikososial)' => 'Recruitment',
    ];

    public function index(): View
    {
        // Sengaja tanpa kueri apa pun: seluruh angka datang lewat data(),
        // sehingga RDS yang sedang tidak terjangkau tidak membuat halaman 500.
        return view('ohs-score-card.incident-management.dashboard', [
            'tabel' => self::TABLE,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $segar = $request->boolean('segar');

        try {
            if ($segar) {
                Cache::forget(self::CACHE_KEY);
            }

            $isi = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn (): array => $this->kumpulkan());
        } catch (Throwable $e) {
            // RDS tidak selalu terjangkau (mis. dari jaringan lokal tanpa tunnel).
            return response()->json([
                'ok' => false,
                'pesan' => 'Tidak bisa menghubungi OBDS (' . self::CONNECTION . '). '
                    . 'Dari jaringan lokal database OLAP memang tidak terjangkau; '
                    . 'halaman ini butuh dijalankan dari server.',
                'detail' => mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()) ?? '', 0, 300),
            ], 200);
        }

        return response()->json(['ok' => true] + $isi);
    }

    // ======================================================================
    // Pengambilan data
    // ======================================================================

    /** @return array<string, mixed> */
    private function kumpulkan(): array
    {
        $insiden = $this->ambilInsiden();
        $temuan = $this->ambilTemuan();
        $car = $this->ambilCar();
        $pasangan = $this->ambilPasanganRcCar();

        // Kamus dimensi dibangun dari data yang benar-benar ada, bukan daftar
        // tetap, supaya nilai baru di sumber tidak diam-diam hilang.
        $site = $this->kamus([
            array_column($insiden, 'site'),
            array_column($temuan, 'site'),
            array_column($car, 'site'),
            array_column($pasangan, 'site'),
        ]);
        $jenis = $this->kamus([array_column($insiden, 'jenis')]);
        $kategori = $this->kamus([array_column($insiden, 'kategori'), array_column($temuan, 'kategori')]);
        $status = $this->kamus([array_column($insiden, 'status')]);
        $statusLayer = $this->kamus([array_column($temuan, 'status_layer')]);
        $statusCar = $this->kamus([array_column($car, 'status')]);

        $aktivitas = $this->kamusAktivitas($temuan);

        $tahun = [];

        foreach ($insiden as $r) {
            $tahun[(int) substr((string) $r->ym, 0, 4)] = true;
        }

        ksort($tahun);

        return [
            'dim' => [
                'site' => $site['label'],
                'jenis' => $jenis['label'],
                'kategori' => $kategori['label'],
                'status' => $status['label'],
                'status_layer' => $statusLayer['label'],
                'status_car' => $statusCar['label'],
                'layer' => self::NAMA_LAYER,
                'layer_pendek' => self::NAMA_LAYER_PENDEK,
                'aktivitas' => $aktivitas['daftar'],
            ],
            'tahun' => array_keys($tahun),
            'd1' => array_map(static fn (object $r): array => [
                (string) $r->ym,
                $site['peta'][$r->site],
                $jenis['peta'][$r->jenis],
                $kategori['peta'][$r->kategori],
                $status['peta'][$r->status],
                (int) $r->n,
                (int) $r->r,
            ], $insiden),
            'd2' => array_map(static fn (object $r): array => [
                (int) $r->tahun,
                $site['peta'][$r->site],
                $kategori['peta'][$r->kategori],
                $aktivitas['indeks'][$r->layer . '|' . $r->aktivitas],
                $statusLayer['peta'][$r->status_layer],
                (int) $r->n,
            ], $temuan),
            'car' => array_map(static fn (object $r): array => [
                (int) $r->tahun,
                $site['peta'][$r->site],
                (int) $r->layer,
                $statusCar['peta'][$r->status],
                (int) $r->overdue,
                (int) $r->n,
            ], $car),
            'rc' => array_map(static fn (object $r): array => [
                (int) $r->tahun,
                $site['peta'][$r->site],
                (int) $r->layer_rc,
                (int) $r->layer_car,
                (int) $r->n,
            ], $pasangan),
            'meta' => [
                'tabel' => self::TABLE,
                'koneksi' => self::CONNECTION,
                'diambil' => now()->format('d/m/Y H:i'),
                'tahun_awal' => self::TAHUN_AWAL,
            ],
        ];
    }

    /** Baris dasar yang dipakai semua kueri, sebagai CTE. */
    private function cteBasis(): string
    {
        $tanggal = self::SQL_TANGGAL;
        $site = self::SQL_SITE;
        $kategori = self::SQL_KATEGORI;
        $tabel = self::TABLE;
        $awal = self::TAHUN_AWAL;

        return <<<SQL
            basis AS (
                SELECT
                    id_investigasi,
                    {$tanggal} AS tgl,
                    {$site} AS site,
                    COALESCE(NULLIF(TRIM(ccr_jenis_insiden), ''), 'Tidak diketahui') AS jenis,
                    {$kategori} AS kategori,
                    status_investigasi AS status,
                    rootcause,
                    tindakan_perbaikan
                FROM {$tabel}
                WHERE status_investigasi <> 'DELETED'
                  AND COALESCE(kategori_kecelakaan, '') <> 'Test'
                  AND {$tanggal} >= DATE '{$awal}-01-01'
            )
            SQL;
    }

    /** @return array<int, object> */
    private function ambilInsiden(): array
    {
        return $this->jalankan(<<<'SQL'
            SELECT to_char(tgl, 'YYYYMM') AS ym, site, jenis, kategori, status,
                   COUNT(*) AS n,
                   COUNT(*) FILTER (WHERE :ada_ipls:) AS r
            FROM basis
            GROUP BY 1, 2, 3, 4, 5
            SQL);
    }

    /** @return array<int, object> */
    private function ambilTemuan(): array
    {
        return $this->jalankan(<<<'SQL'
            , rc AS (
                SELECT b.site, b.kategori, b.tgl, e
                FROM basis b, jsonb_array_elements(b.rootcause) e
                WHERE jsonb_typeof(b.rootcause) = 'array'
            )
            SELECT EXTRACT(YEAR FROM tgl)::int AS tahun, site, kategori,
                   :layer_rc: AS layer,
                   COALESCE(NULLIF(TRIM(e->>'activity_layer'), ''), 'Tidak diketahui') AS aktivitas,
                   COALESCE(NULLIF(TRIM(e->>'status_layer'), ''), 'Tidak diketahui') AS status_layer,
                   COUNT(*) AS n
            FROM rc
            GROUP BY 1, 2, 3, 4, 5, 6
            SQL);
    }

    /** @return array<int, object> */
    private function ambilCar(): array
    {
        return $this->jalankan(<<<'SQL'
            , ca AS (
                SELECT b.site, b.tgl, e
                FROM basis b, jsonb_array_elements(b.tindakan_perbaikan) e
                WHERE jsonb_typeof(b.tindakan_perbaikan) = 'array'
            )
            SELECT EXTRACT(YEAR FROM tgl)::int AS tahun, site,
                   :layer_car: AS layer,
                   COALESCE(NULLIF(TRIM(e->>'status_perbaikan'), ''), 'Lainnya') AS status,
                   CASE
                       WHEN UPPER(COALESCE(e->>'status_perbaikan', '')) IN ('OPEN', 'PENDING APPROVAL')
                        AND NULLIF(e->>'target_waktu', '')::timestamp < now()
                       THEN 1 ELSE 0
                   END AS overdue,
                   COUNT(*) AS n
            FROM ca
            GROUP BY 1, 2, 3, 4, 5
            SQL);
    }

    /**
     * Pasangan layer root cause x layer CAR, dihitung per INSIDEN.
     *
     * DISTINCT-nya wajib: tanpa itu satu insiden dengan 3 root cause dan 4 CAR
     * akan menyumbang 12 baris ke satu pasangan yang sama, dan Sankey-nya
     * memperbesar alur yang sebenarnya cuma satu insiden.
     *
     * @return array<int, object>
     */
    private function ambilPasanganRcCar(): array
    {
        return $this->jalankan(<<<'SQL'
            , rc AS (
                SELECT b.id_investigasi, b.site, b.tgl, :layer_rc: AS layer
                FROM basis b, jsonb_array_elements(b.rootcause) e
                WHERE jsonb_typeof(b.rootcause) = 'array'
                  AND UPPER(COALESCE(e->>'status_layer', '')) = 'ROOT CAUSE'
            ), ca AS (
                SELECT b.id_investigasi, :layer_car: AS layer
                FROM basis b, jsonb_array_elements(b.tindakan_perbaikan) e
                WHERE jsonb_typeof(b.tindakan_perbaikan) = 'array'
            ), pasangan AS (
                SELECT DISTINCT r.id_investigasi, r.site, r.tgl,
                       r.layer AS layer_rc, c.layer AS layer_car
                FROM rc r
                JOIN ca c ON c.id_investigasi = r.id_investigasi
            )
            SELECT EXTRACT(YEAR FROM tgl)::int AS tahun, site, layer_rc, layer_car, COUNT(*) AS n
            FROM pasangan
            GROUP BY 1, 2, 3, 4
            SQL);
    }

    // ======================================================================
    // Utilitas
    // ======================================================================

    /**
     * Menyambung CTE basis dengan potongan kueri, mengganti penanda :nama:,
     * lalu menjalankannya.
     *
     * Penandanya memakai titik dua di KEDUA sisi, bukan :nama saja, supaya
     * tidak bentrok dengan sintaks parameter terikat PDO; kueri ini memang
     * tidak punya parameter dari pengguna, seluruh isinya konstanta kelas.
     *
     * @return array<int, object>
     */
    private function jalankan(string $potongan): array
    {
        $sql = 'WITH ' . $this->cteBasis() . "\n" . strtr($potongan, [
            ':ada_ipls:' => self::SQL_ADA_IPLS,
            ':layer_rc:' => sprintf(self::SQL_LAYER, "e->>'layer'"),
            ':layer_car:' => sprintf(self::SQL_LAYER, "e->>'layer_tindakan_perbaikan'"),
        ]);

        return DB::connection(self::CONNECTION)->select($sql) ?: [];
    }

    /**
     * Kamus dimensi: daftar label terurut, plus peta dari NILAI MENTAH ke
     * indeks label itu.
     *
     * Dua-duanya dikembalikan karena nilai mentah dan labelnya tidak selalu
     * sama -- 'TIDAK INVESTIGASI' tampil sebagai 'Tidak investigasi' -- dan
     * baris data masih memegang nilai mentah saat dipetakan ke indeks.
     * Mencari dengan label sementara menyimpan dengan nilai mentah (atau
     * sebaliknya) membuat seluruh dimensi meleset tanpa suara.
     *
     * @param  array<int, array<int, string>>  $kumpulan
     * @return array{label: array<int, string>, peta: array<string, int>}
     */
    private function kamus(array $kumpulan): array
    {
        $mentahKeLabel = [];

        foreach ($kumpulan as $daftar) {
            foreach ($daftar as $v) {
                $mentah = (string) $v;
                $mentahKeLabel[$mentah] = $this->label(trim($mentah));
            }
        }

        $label = array_values(array_unique(array_values($mentahKeLabel)));
        sort($label);

        $indeks = array_flip($label);
        $peta = [];

        foreach ($mentahKeLabel as $mentah => $l) {
            $peta[(string) $mentah] = $indeks[$l];
        }

        return ['label' => $label, 'peta' => $peta];
    }

    /** Menerjemahkan kode sumber yang serba huruf besar jadi label yang enak dibaca. */
    private function label(string $nilai): string
    {
        return self::LABEL_STATUS[$nilai]
            ?? self::LABEL_STATUS_LAYER[$nilai]
            ?? self::LABEL_STATUS_CAR[$nilai]
            ?? $nilai;
    }

    /**
     * Daftar aktivitas beserta layer-nya, plus peta "layer|nama" -> indeks.
     *
     * @param  array<int, object>  $temuan
     * @return array{daftar: array<int, array<string, mixed>>, indeks: array<string, int>}
     */
    private function kamusAktivitas(array $temuan): array
    {
        $kunci = [];

        foreach ($temuan as $r) {
            $kunci[$r->layer . '|' . $r->aktivitas] = true;
        }

        $urut = array_keys($kunci);
        sort($urut);

        $daftar = [];
        $indeks = [];

        foreach ($urut as $i => $k) {
            [$layer, $nama] = explode('|', $k, 2);
            // Awalan penomoran sumber ("10. Training Kompetensi") dibuang:
            // nomornya tidak berurutan di tampilan dan hanya memakan lebar.
            $bersih = preg_replace('/^\d+\.\s*/', '', $nama) ?? $nama;

            $daftar[] = [
                'layer' => (int) $layer,
                'nama' => self::AKTIVITAS_PENDEK[$bersih] ?? $bersih,
            ];
            $indeks[$k] = $i;
        }

        return ['daftar' => $daftar, 'indeks' => $indeks];
    }
}
