<?php

declare(strict_types=1);

namespace App\Services\OhsScoreCard;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Jumlah pengguna BeSigma terdaftar per site dan perusahaan.
 *
 * Dipakai sebagai PENYEBUT parameter "Utilisasi BeSigma". Pembilangnya ada di
 * MySQL (lead_utilisasi_besigma.distinct_kode_sid) sedangkan daftar
 * penggunanya di Postgres OLAP, jadi keduanya tidak bisa di-JOIN dan
 * pembagiannya dilakukan di PHP.
 *
 * TABEL CERMINAN LOKAL users_besigma SENGAJA TIDAK DIPAKAI. Pernah diukur dan
 * hasilnya tidak sahih: dari 60 pasangan site/perusahaan di tabel lead hanya 6
 * yang ketemu, dan pada 49 pasangan lain nama perusahaannya ada tetapi tidak
 * satu pun penggunanya terdaftar di site itu. Yang sahih adalah database
 * BeSigma yang hidup.
 *
 * TIDAK TERJANGKAU DARI LUAR SERVER. RDS-nya dibatasi security group: dari
 * laptop, TCP-nya terjangkau tetapi handshake Postgres timeout. Karena itu
 * setiap kegagalan menghasilkan peta KOSONG, bukan lemparan galat -- halaman
 * yang memakainya harus tetap bisa dibuka di mana pun, dengan menampilkan
 * cacah apa adanya dan mengatakan Nilainya belum bisa dihitung.
 *
 * NAMA TABEL DAN KOLOM belum sempat diperiksa langsung karena koneksinya
 * tertutup dari sini; diambil dari bentuk cerminan lokalnya dan ditulis
 * sebagai konstanta agar gampang dibetulkan di server kalau ternyata berbeda.
 */
final class BesigmaPenggunaTerdaftar
{
    /** Connection Laravel ke database BeSigma; lihat config/database.php. */
    public const KONEKSI = 'besigma_db';

    private const SCHEMA = 'besigma_db';

    private const TABEL_PENGGUNA = 'users';

    private const TABEL_PERUSAHAAN = 'companies';

    private const COL_SID = 'sid_code';

    private const COL_SITE = 'dedicated_site';

    private const COL_COMPANY_ID = 'company_id';

    private const COL_NAMA_PERUSAHAAN = 'name';

    /** Peta jarang berubah; menariknya tiap permintaan terlalu mahal. */
    private const CACHE_KEY = 'ohs-sc:besigma-terdaftar-v1';

    /** Hasil yang berhasil boleh disimpan lama. */
    private const TTL_BERHASIL = 1800;

    /**
     * Kegagalan disimpan SEBENTAR saja. Menyimpannya selama TTL_BERHASIL akan
     * membuat halaman tetap memakai mode cacah sampai setengah jam setelah
     * BeSigma pulih; sebaliknya, tanpa cache sama sekali setiap permintaan
     * harus menunggu koneksi timeout dulu.
     */
    private const TTL_GAGAL = 120;

    /** Cache per-permintaan supaya satu halaman tidak menarik peta berkali-kali. */
    private ?array $peta = null;

    /**
     * Peta "site|perusahaan" (huruf kecil) => jumlah pengguna terdaftar.
     * Kosong berarti database BeSigma tidak terjangkau.
     *
     * @return array<string, int>
     */
    public function peta(): array
    {
        if ($this->peta !== null) {
            return $this->peta;
        }

        $tersimpan = Cache::get(self::CACHE_KEY);

        if (is_array($tersimpan)) {
            return $this->peta = $tersimpan;
        }

        $peta = $this->tarik();

        Cache::put(
            self::CACHE_KEY,
            $peta,
            $peta === [] ? self::TTL_GAGAL : self::TTL_BERHASIL
        );

        return $this->peta = $peta;
    }

    public function tersedia(): bool
    {
        return $this->peta() !== [];
    }

    /**
     * Jumlah pengguna terdaftar untuk satu pasangan.
     *
     * @return int|null null kalau petanya tidak tersedia sama sekali; 0 kalau
     *                  petanya ada tetapi pasangan itu tidak terdaftar.
     */
    public function untuk(string $site, string $mitra): ?int
    {
        $peta = $this->peta();

        if ($peta === []) {
            return null;
        }

        return (int) ($peta[self::kunci($site, $mitra)] ?? 0);
    }

    /** Kunci peta; disamakan hurufnya supaya beda kapital tidak memutus jodoh. */
    public static function kunci(string $site, string $mitra): string
    {
        return mb_strtolower(trim($site)) . '|' . mb_strtolower(trim($mitra));
    }

    /** @return array<string, int> */
    private function tarik(): array
    {
        try {
            $rows = DB::connection(self::KONEKSI)
                ->table(self::SCHEMA . '.' . self::TABEL_PENGGUNA . ' as u')
                ->join(
                    self::SCHEMA . '.' . self::TABEL_PERUSAHAAN . ' as c',
                    'c.id',
                    '=',
                    'u.' . self::COL_COMPANY_ID
                )
                ->selectRaw(
                    'u."' . self::COL_SITE . '" AS site, '
                    . 'c."' . self::COL_NAMA_PERUSAHAAN . '" AS mitra, '
                    . 'COUNT(DISTINCT u."' . self::COL_SID . '") AS jumlah'
                )
                ->groupBy('u.' . self::COL_SITE, 'c.' . self::COL_NAMA_PERUSAHAAN)
                ->get();

            $peta = [];

            foreach ($rows as $r) {
                $site = trim((string) $r->site);
                $mitra = trim((string) $r->mitra);

                if ($site === '' || $mitra === '') {
                    continue;
                }

                $peta[self::kunci($site, $mitra)] = (int) $r->jumlah;
            }

            return $peta;
        } catch (Throwable $e) {
            // Sengaja ditelan: BeSigma tidak terjangkau dari luar server, dan
            // halaman yang memakainya harus tetap bisa dibuka di mana pun.
            report($e);

            return [];
        }
    }
}
