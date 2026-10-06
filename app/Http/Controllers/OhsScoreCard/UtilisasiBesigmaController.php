<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsScoreCard;

use App\Services\OhsScoreCard\BesigmaPenggunaTerdaftar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Parameter "Utilisasi BeSigma".
 *
 *     persen = SID aktif bulan itu / pengguna BeSigma terdaftar x 100
 *
 * PEMBILANG DAN PENYEBUTNYA ADA DI DUA DATABASE BERBEDA.
 *
 *   - Pembilang: lead_utilisasi_besigma di MySQL, kolom distinct_kode_sid,
 *     yaitu cacah SID yang aktif pada satu site, perusahaan, dan bulan.
 *   - Penyebut: daftar pengguna di database BeSigma (Postgres OLAP, connection
 *     `besigma_db`, schema besigma_db).
 *
 * Karena beda database keduanya TIDAK BISA DI-JOIN. Penyebutnya ditarik sekali
 * sebagai peta "site|perusahaan => jumlah pengguna", di-cache, lalu dibagikan
 * di PHP lewat penyebutLuar().
 *
 * TABEL CERMINAN LOKAL users_besigma SENGAJA TIDAK DIPAKAI. Pernah dicoba dan
 * hasilnya tidak sahih: dari 60 pasangan site/perusahaan di tabel lead hanya 6
 * yang ketemu, dan pada 49 pasangan lain nama perusahaannya ada tetapi tidak
 * satu pun penggunanya terdaftar di site itu. Sumber yang sahih adalah
 * database BeSigma yang hidup.
 *
 * HALAMAN TETAP HIDUP KETIKA BESIGMA TIDAK TERJANGKAU. RDS-nya hanya bisa
 * dihubungi dari server; dari luar, TCP-nya terjangkau tetapi handshake
 * Postgres timeout karena security group. Kalau petanya gagal diambil, halaman
 * turun ke MODE CACAH -- menampilkan jumlah SID aktif apa adanya, tanpa Nilai,
 * dan mengatakannya di catatan. Begitu jalan di server, petanya terbaca dan
 * halaman otomatis menjadi persentase lengkap dengan band
 * <96% / 96-98% / 98-100% / 100%.
 *
 * Peta penyebutnya sendiri tinggal di BesigmaPenggunaTerdaftar, supaya
 * halaman ini dan tabel Score Card di dashboard memakai angka yang sama
 * persis. Nama tabel dan kolom Postgres-nya ada di sana.
 */
final class UtilisasiBesigmaController extends AbstractLeadBulananController
{
    public function __construct(
        private readonly BesigmaPenggunaTerdaftar $terdaftar,
    ) {}

    protected function tabel(): string
    {
        return 'lead_utilisasi_besigma';
    }

    protected function judul(): string
    {
        return 'Utilisasi BeSigma';
    }

    protected function slug(): string
    {
        return 'utilisasi-besigma';
    }

    protected function penjelasan(): string
    {
        return $this->adaPenyebut()
            ? 'SID aktif dibagi pengguna BeSigma terdaftar, tiap perusahaan di tiap site'
            : 'Cacah SID aktif di BeSigma, tiap perusahaan di tiap site';
    }

    protected function kolom(): array
    {
        return [
            'site' => 'site_dedicated',
            'mitra' => 'company',
            'bulan' => 'month_name',
            'nilai' => 'distinct_kode_sid',
        ];
    }

    protected function ekspresiPersen(): string
    {
        // Penyebutnya tidak ada di tabel ini; diisi 1 supaya sel tetap dianggap
        // berdata oleh kelas induk, lalu diganti penyebut sebenarnya di
        // penyebutLuar(). Tanpa peta, kolom "persen" berisi cacah apa adanya.
        return 'SUM(`distinct_kode_sid`) AS persen, '
            . 'SUM(`distinct_kode_sid`) AS pembilang, '
            . '1 AS penyebut';
    }

    /**
     * Penyebut dari database BeSigma.
     *
     * Mengembalikan null ketika petanya tidak tersedia, sehingga kelas induk
     * memakai angka dari SQL apa adanya dan halaman jatuh ke mode cacah.
     */
    protected function penyebutLuar(string $site, string $mitra): ?float
    {
        // Pasangan yang tidak ada di BeSigma dapat 0, bukan null. Nol membuat
        // sel itu tidak dinilai, alih-alih diam-diam memakai cacah sebagai
        // persen; null hanya berarti petanya memang tidak tersedia.
        $jumlah = $this->terdaftar->untuk($site, $mitra);

        return $jumlah === null ? null : (float) $jumlah;
    }

    protected function satuan(): string
    {
        return $this->adaPenyebut() ? '%' : '';
    }

    protected function nilaiUntuk(float $persen): array
    {
        if (!$this->adaPenyebut()) {
            return [null, null];
        }

        if ($persen >= 100.0) {
            return [4.0, '100%'];
        }

        if ($persen >= 98.0) {
            return [round(min(3.0 + ($persen - 98.0) / 2.0, 3.99), 2), '98% - <100%'];
        }

        if ($persen >= 96.0) {
            return [round(min(2.0 + ($persen - 96.0) / 2.0, 2.99), 2), '96% - <98%'];
        }

        return [round(min(1.0 + $persen / 96.0, 1.99), 2), '<96%'];
    }

    protected function legendaBand(): array
    {
        if (!$this->adaPenyebut()) {
            return [];
        }

        return [
            ['nilai' => 1, 'label' => '<96%'],
            ['nilai' => 2, 'label' => '96-<98%'],
            ['nilai' => 3, 'label' => '98-<100%'],
            ['nilai' => 4, 'label' => 'tepat 100%'],
        ];
    }

    protected function labelPecahan(): array
    {
        return $this->adaPenyebut()
            ? ['SID aktif', 'Pengguna terdaftar']
            : ['SID aktif', 'Pembagi'];
    }

    protected function target(): float
    {
        return 100.0;
    }

    /**
     * Catatan di halaman mengatakan apa adanya: dari mana penyebutnya, berapa
     * pasangan yang ketemu, atau kenapa Nilainya belum ada.
     */
    protected function catatan(Request $request): ?string
    {
        if (!$this->adaPenyebut()) {
            return 'Angka di halaman ini adalah CACAH SID aktif, bukan persentase, karena '
                . 'database BeSigma sedang tidak terjangkau sehingga jumlah pengguna terdaftar '
                . 'belum bisa dibaca. Band resmi parameter ini berbasis persen, jadi Nilai 1-4 '
                . 'sengaja dikosongkan daripada dihitung dari angka yang salah. Di server, '
                . 'tempat koneksi ke BeSigma terbuka, halaman ini otomatis menampilkan '
                . 'persentase beserta Nilainya.';
        }

        $peta = $this->terdaftar->peta();

        $pasangan = DB::table($this->tabel())
            ->distinct()
            ->selectRaw('`site_dedicated` AS s, `company` AS c')
            ->get();

        $ketemu = 0;

        foreach ($pasangan as $p) {
            if (($this->terdaftar->untuk((string) $p->s, (string) $p->c) ?? 0) > 0) {
                $ketemu++;
            }
        }

        $catatan = sprintf(
            'Penyebutnya jumlah pengguna BeSigma terdaftar, dibaca langsung dari database '
            . 'BeSigma (%s pasangan site/perusahaan terdata di sana). Dari %s pasangan di %s, '
            . '%s ketemu penyebutnya.',
            number_format(count($peta), 0, ',', '.'),
            number_format(count($pasangan), 0, ',', '.'),
            $this->tabel(),
            number_format($ketemu, 0, ',', '.')
        );

        if ($ketemu < count($pasangan)) {
            $catatan .= sprintf(
                ' %s pasangan sisanya tidak punya pengguna terdaftar di BeSigma pada site dan '
                . 'perusahaan yang sama, jadi sengaja tidak dinilai dan tampil sebagai sel '
                . 'kosong, bukan 0%%.',
                number_format(count($pasangan) - $ketemu, 0, ',', '.')
            );
        }

        return $catatan;
    }

    private function adaPenyebut(): bool
    {
        return $this->terdaftar->tersedia();
    }
}
