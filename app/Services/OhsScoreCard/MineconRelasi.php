<?php

declare(strict_types=1);

namespace App\Services\OhsScoreCard;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Mencari perusahaan MINECON (mitra kerja langsung PT Berau Coal) di atas
 * sebuah subkontraktor, untuk parameter "% Blindspot TBC dengan PIC
 * Subcontractor".
 *
 * SUMBERNYA bcsid.bep_vw_struktur_relasi_perusahaan di Postgres OLAP
 * (connection `pgsql_direct`). View itu sudah meratakan hierarkinya:
 * mk_lv1 adalah minecon, mk_lv2 sampai mk_lv5 subkon berjenjang di bawahnya.
 *
 * DATA BLINDSPOT ADA DI MYSQL, relasinya di Postgres, jadi keduanya tidak bisa
 * di-JOIN. Petanya ditarik sekali, di-cache, lalu dijodohkan di PHP.
 *
 * PEMETAANNYA TIDAK SELALU TUNGGAL, dan itu sifat datanya, bukan kelalaian.
 * Diukur pada 44 pasangan site/subkon yang ada di halaman: 23 punya tepat satu
 * minecon, 8 punya dua sampai empat sekaligus di site yang sama, dan 13 tidak
 * punya relasi apa pun di site itu. Aturan penyelesaiannya -- dipilih bersama
 * pengguna, bukan ditebak:
 *
 *   1. Tepat satu minecon            -> dipakai.
 *   2. Lebih dari satu               -> disaring ke kontraktor yang memang
 *                                       punya kolom di Score Card untuk site
 *                                       itu. Kalau tersisa tepat satu, itu
 *                                       yang dipakai; kalau masih lebih dari
 *                                       satu, ditandai GANDA dan TIDAK
 *                                       dibebankan ke siapa pun, daripada
 *                                       salah menempel ke kontraktor lain.
 *   3. Tidak ada relasi di site itu  -> kalau subkonnya sendiri terdaftar
 *                                       sebagai minecon, dia memang bermitra
 *                                       langsung dengan Berau Coal, jadi
 *                                       minecon-nya dirinya sendiri.
 *   4. Selain itu                    -> BELUM TERPETAKAN.
 *
 * TIDAK TERJANGKAU DARI LUAR SERVER. RDS-nya dibatasi security group; dari
 * laptop, handshake Postgres timeout. Setiap kegagalan menghasilkan peta
 * KOSONG, bukan lemparan galat -- halaman harus tetap bisa dibuka, dengan
 * seluruh Minecon ditandai belum terpetakan.
 */
final class MineconRelasi
{
    public const KONEKSI = 'pgsql_direct';

    private const VIEW = 'bcsid.bep_vw_struktur_relasi_perusahaan';

    /** Status hasil pemetaan, dipakai layar untuk membedakan sebabnya. */
    public const STATUS_TUNGGAL = 'tunggal';

    public const STATUS_DISARING = 'disaring';

    public const STATUS_SENDIRI = 'sendiri';

    public const STATUS_GANDA = 'ganda';

    public const STATUS_KOSONG = 'belum-terpetakan';

    public const LABEL_GANDA = 'Beberapa Minecon';

    public const LABEL_KOSONG = 'Belum terpetakan';

    private const CACHE_KEY = 'ohs-sc:minecon-relasi-v1';

    private const TTL_BERHASIL = 3600;

    /** Kegagalan disimpan sebentar saja supaya pemulihan cepat terasa. */
    private const TTL_GAGAL = 120;

    /** @var array<string, mixed>|null */
    private ?array $mentah = null;

    /** @var array<string, array<string, mixed>> */
    private array $cacheSel = [];

    /**
     * Minecon untuk satu pasangan site/subkon.
     *
     * @return array{minecon: string|null, status: string, kandidat: array<int, string>}
     */
    public function untuk(string $site, string $subkon): array
    {
        $kunci = $this->kunci($site, $subkon);

        if (isset($this->cacheSel[$kunci])) {
            return $this->cacheSel[$kunci];
        }

        return $this->cacheSel[$kunci] = $this->selesaikan(trim($site), trim($subkon));
    }

    public function tersedia(): bool
    {
        $m = $this->mentah();

        return ($m['anak'] ?? []) !== [];
    }

    /**
     * Label pendek untuk ditampilkan: nama minecon, atau keterangan kenapa
     * tidak ada.
     */
    public function label(string $site, string $subkon): string
    {
        $h = $this->untuk($site, $subkon);

        if ($h['minecon'] !== null) {
            return $h['minecon'];
        }

        return $h['status'] === self::STATUS_GANDA ? self::LABEL_GANDA : self::LABEL_KOSONG;
    }

    /** @return array{minecon: string|null, status: string, kandidat: array<int, string>} */
    private function selesaikan(string $site, string $subkon): array
    {
        $m = $this->mentah();

        if (($m['anak'] ?? []) === []) {
            return ['minecon' => null, 'status' => self::STATUS_KOSONG, 'kandidat' => []];
        }

        $kandidat = array_values(array_unique($m['anak'][$this->kunci($site, $subkon)] ?? []));

        if (count($kandidat) === 1) {
            return ['minecon' => $kandidat[0], 'status' => self::STATUS_TUNGGAL, 'kandidat' => $kandidat];
        }

        if (count($kandidat) > 1) {
            $disaring = $this->saringKeKolom($site, $kandidat);

            if (count($disaring) === 1) {
                return [
                    'minecon' => $disaring[0],
                    'status' => self::STATUS_DISARING,
                    'kandidat' => $kandidat,
                ];
            }

            // Masih lebih dari satu: sengaja tidak dipilihkan. Membebankan
            // temuan ke kontraktor yang salah lebih buruk daripada mengosongkan.
            return ['minecon' => null, 'status' => self::STATUS_GANDA, 'kandidat' => $kandidat];
        }

        // Tidak ada relasi di site itu. Kalau perusahaannya sendiri terdaftar
        // sebagai minecon, dia memang bermitra langsung dengan Berau Coal.
        if (isset($m['minecon'][mb_strtolower($subkon)])) {
            return ['minecon' => $subkon, 'status' => self::STATUS_SENDIRI, 'kandidat' => [$subkon]];
        }

        return ['minecon' => null, 'status' => self::STATUS_KOSONG, 'kandidat' => []];
    }

    /**
     * Menyisakan kandidat yang punya kolom di Score Card untuk site itu.
     *
     * @param  array<int, string>  $kandidat
     * @return array<int, string>
     */
    private function saringKeKolom(string $site, array $kandidat): array
    {
        $kolom = ScoreCardParameterRegistry::KOLOM[$site] ?? [];

        if ($kolom === []) {
            return [];
        }

        $sisa = [];

        foreach ($kandidat as $nama) {
            $label = ScoreCardParameterRegistry::ALIAS_KONTRAKTOR[$this->kunciNama($nama)] ?? null;

            if ($label !== null && in_array($label, $kolom, true)) {
                $sisa[] = $nama;
            }
        }

        return array_values(array_unique($sisa));
    }

    /**
     * Peta mentah dari view relasi.
     *
     *   anak    : "site|subkon" => daftar nama minecon
     *   minecon : nama minecon (huruf kecil) => true
     *
     * @return array{anak: array<string, array<int, string>>, minecon: array<string, true>}
     */
    private function mentah(): array
    {
        if ($this->mentah !== null) {
            return $this->mentah;
        }

        $tersimpan = Cache::get(self::CACHE_KEY);

        if (is_array($tersimpan)) {
            return $this->mentah = $tersimpan;
        }

        $peta = $this->tarik();

        Cache::put(
            self::CACHE_KEY,
            $peta,
            $peta['anak'] === [] ? self::TTL_GAGAL : self::TTL_BERHASIL
        );

        return $this->mentah = $peta;
    }

    /** @return array{anak: array<string, array<int, string>>, minecon: array<string, true>} */
    private function tarik(): array
    {
        $kosong = ['anak' => [], 'minecon' => []];

        try {
            // Tiap level subkon diratakan ke pasangan (anak, minecon, site).
            // site_mk_lv2 dipakai lebih dulu karena itu site tempat subkonnya
            // benar-benar bekerja; site_mk_lv1 hanya cadangan.
            $sql = [];

            foreach ([2, 3, 4, 5] as $lv) {
                $sql[] = 'SELECT DISTINCT mk_lv' . $lv . ' AS anak, mk_lv1 AS minecon,'
                    . ' COALESCE(site_mk_lv2, site_mk_lv1) AS site'
                    . ' FROM ' . self::VIEW
                    . ' WHERE mk_lv' . $lv . ' IS NOT NULL AND mk_lv1 IS NOT NULL';
            }

            $rows = DB::connection(self::KONEKSI)->select(implode(' UNION ', $sql));

            $anak = [];

            foreach ($rows as $r) {
                $site = trim((string) $r->site);
                $nama = trim((string) $r->anak);
                $minecon = trim((string) $r->minecon);

                if ($site === '' || $nama === '' || $minecon === '' || $nama === $minecon) {
                    continue;
                }

                $anak[$this->kunci($site, $nama)][] = $minecon;
            }

            $daftarMinecon = [];

            foreach (DB::connection(self::KONEKSI)->select(
                'SELECT DISTINCT mk_lv1 AS nama FROM ' . self::VIEW . ' WHERE mk_lv1 IS NOT NULL'
            ) as $r) {
                $nama = trim((string) $r->nama);

                if ($nama !== '') {
                    $daftarMinecon[mb_strtolower($nama)] = true;
                }
            }

            return ['anak' => $anak, 'minecon' => $daftarMinecon];
        } catch (Throwable $e) {
            // Sengaja ditelan: OLAP tidak terjangkau dari luar server, dan
            // halaman yang memakainya harus tetap bisa dibuka.
            report($e);

            return $kosong;
        }
    }

    private function kunci(string $site, string $nama): string
    {
        return mb_strtolower(trim($site)) . '|' . mb_strtolower(trim($nama));
    }

    /** Menyamakan bentuk nama supaya cocok dengan ALIAS_KONTRAKTOR. */
    private function kunciNama(string $mentah): string
    {
        $s = mb_strtolower(trim($mentah));
        $s = (string) preg_replace('/^(pt|cv)[\s.]+/u', '', $s);

        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }
}
