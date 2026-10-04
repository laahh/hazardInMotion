<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pembacaan OBDS (database OLAP hse_automation) untuk halaman yang menarik
 * data langsung dari sana, bukan dari app_mixer.
 *
 * KONEKSINYA pgsql_direct, mengikuti BerecordController: tunnel SSH di server
 * tidak selalu hidup, akses langsung ke RDS lebih andal.
 *
 * DARI JARINGAN LOKAL RDS TIDAK TERJANGKAU. Karena itu setiap pemanggil wajib
 * membungkus kuerinya dan memakai gagalObds() supaya halaman tetap terender
 * dengan pesan yang jelas, bukan melempar 500.
 */
trait MembacaObds
{
    private function koneksiObds(): string
    {
        return 'pgsql_direct';
    }

    /**
     * Menjalankan kueri baca ke OBDS.
     *
     * Seluruh SQL di halaman ini dirakit dari konstanta kelas; satu-satunya
     * nilai dari pengguna adalah id investigasi, dan itu dikirim sebagai
     * parameter terikat, bukan disulam ke dalam teks SQL.
     *
     * @param  array<int, mixed>  $parameter
     * @return array<int, object>
     */
    private function bacaObds(string $sql, array $parameter = []): array
    {
        return DB::connection($this->koneksiObds())->select($sql, $parameter) ?: [];
    }

    /** Satu baris pertama, atau null kalau kuerinya tidak menghasilkan apa pun. */
    private function bacaSatuObds(string $sql, array $parameter = []): ?object
    {
        return $this->bacaObds($sql, $parameter)[0] ?? null;
    }

    /**
     * Kolom JSON dari Postgres sudah berupa teks; diurai di sini supaya
     * pemanggilnya menerima array biasa.
     *
     * @return array<int|string, mixed>
     */
    private function uraikanJson(?string $nilai): array
    {
        if ($nilai === null || trim($nilai) === '' || trim($nilai) === 'null') {
            return [];
        }

        $hasil = json_decode($nilai, true);

        return is_array($hasil) ? $hasil : [];
    }

    /**
     * Jawaban seragam saat OBDS tidak terjangkau.
     *
     * Sengaja HTTP 200 dengan ok:false, bukan 5xx: ini keadaan yang memang
     * diharapkan dari jaringan lokal, dan halaman perlu menampilkannya sebagai
     * pesan, bukan sebagai kegagalan permintaan.
     */
    private function gagalObds(Throwable $e): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'pesan' => 'Tidak bisa menghubungi OBDS (' . $this->koneksiObds() . '). '
                . 'Dari jaringan lokal database OLAP memang tidak terjangkau; '
                . 'bagian ini butuh dijalankan dari server.',
            'detail' => mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()) ?? '', 0, 300),
        ]);
    }
}
