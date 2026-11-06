<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Models\DmsRosterMasterUpload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Review oleh manager departemen: keputusannya hanya berlaku untuk karyawan
 * departemennya.
 *
 * Status approval ada di tingkat upload dan dibaca semua layar serta proses
 * roster, jadi baris departemen dipindah ke upload baru yang langsung direview.
 * Sisanya tetap menunggu di upload asal, sehingga tiap departemen berlaku
 * begitu manager-nya menyetujui.
 */
final class DmsRosterReviewDepartemen
{
    private const TABEL_JADWAL = 'dms_roster_master_jadwal';

    private const TABEL_LOG = 'dms_roster_master_edit_log';

    private const MAKS_JUDUL = 150;

    public function __construct(
        private readonly DmsRosterMasterService $master,
    ) {}

    /**
     * @param  list<string>  $sids  karyawan departemen (DmsRosterApproverDepartemen::sidUntuk)
     * @param  callable(DmsRosterMasterUpload): (array{path:string,sumber:string}|null)  $ttd  menyimpan tanda tangan atas upload yang direview
     * @return DmsRosterMasterUpload upload yang direview: upload asal bila seluruh isinya departemen ini
     */
    public function review(
        DmsRosterMasterUpload $upload,
        array $sids,
        string $departemen,
        bool $setuju,
        ?string $catatan,
        string $oleh,
        callable $ttd,
    ): DmsRosterMasterUpload {
        return DB::transaction(function () use ($upload, $sids, $departemen, $setuju, $catatan, $oleh, $ttd): DmsRosterMasterUpload {
            $asal = DmsRosterMasterUpload::query()->lockForUpdate()->findOrFail($upload->id);
            if (! $asal->isPending()) {
                throw new RuntimeException('Roster ini sudah direview ('.$asal->status.').');
            }

            $milik = $this->jumlahKaryawan($asal, $sids);
            if ($milik === 0) {
                throw new RuntimeException('Roster ini tidak memuat karyawan departemen '.$departemen.'.');
            }

            $target = $milik === $this->jumlahKaryawan($asal)
                ? $asal
                : $this->pisahkan($asal, $sids, $departemen);
            $this->master->review($target, $setuju, $catatan, $oleh, $ttd($target));

            return $target;
        });
    }

    /**
     * @param  list<string>|null  $sids  null = semua karyawan di upload
     */
    public function jumlahKaryawan(DmsRosterMasterUpload $upload, ?array $sids = null): int
    {
        return DB::table(self::TABEL_JADWAL)
            ->where('upload_id', $upload->id)
            ->when($sids !== null, static fn ($q) => $q->whereIn('kode_sid', $sids))
            ->distinct()
            ->count('kode_sid');
    }

    /**
     * @param  list<string>  $sids
     */
    private function pisahkan(DmsRosterMasterUpload $asal, array $sids, string $departemen): DmsRosterMasterUpload
    {
        $baru = new DmsRosterMasterUpload([
            'judul' => $this->judul($asal->judul, $departemen),
            'periode_mulai' => $asal->periode_mulai,
            'periode_selesai' => $asal->periode_selesai,
            'departemen_site' => $departemen,
            'nama_file' => $asal->nama_file,
            'path_file' => '',
            'status' => DmsRosterMasterUpload::STATUS_PENDING,
            'catatan' => $asal->catatan,
            'diupload_oleh' => $asal->diupload_oleh,
        ]);
        // "Roster terbaru berlaku" memakai created_at; tanggal upload tidak boleh bergeser.
        $baru->created_at = $asal->created_at;
        $baru->save();

        DB::table(self::TABEL_JADWAL)
            ->where('upload_id', $asal->id)
            ->whereIn('kode_sid', $sids)
            ->update(['upload_id' => $baru->id]);
        if (Schema::hasTable(self::TABEL_LOG)) {
            DB::table(self::TABEL_LOG)
                ->where('upload_id', $asal->id)
                ->whereIn('kode_sid', $sids)
                ->update(['upload_id' => $baru->id]);
        }

        $baru->path_file = $this->salinFile($asal, $baru);
        $this->segarkanRingkasan($baru);
        $this->segarkanRingkasan($asal);

        return $baru;
    }

    private function judul(string $judul, string $departemen): string
    {
        $akhiran = ' - '.$departemen;

        return mb_substr($judul, 0, max(0, self::MAKS_JUDUL - mb_strlen($akhiran))).mb_substr($akhiran, 0, self::MAKS_JUDUL);
    }

    /** Menghapus upload menghapus foldernya, jadi tiap upload memegang salinan sendiri. */
    private function salinFile(DmsRosterMasterUpload $asal, DmsRosterMasterUpload $baru): string
    {
        $disk = Storage::disk('local');
        if ($asal->path_file === '' || ! $disk->exists($asal->path_file)) {
            return '';
        }
        $tujuan = 'roster-master/'.$baru->id.'/'.basename($asal->path_file);

        return $disk->copy($asal->path_file, $tujuan) ? $tujuan : '';
    }

    private function segarkanRingkasan(DmsRosterMasterUpload $upload): void
    {
        $r = DB::table(self::TABEL_JADWAL)
            ->where('upload_id', $upload->id)
            ->selectRaw('COUNT(*) AS jadwal, COUNT(DISTINCT kode_sid) AS karyawan, MIN(tanggal) AS dari, MAX(tanggal) AS sampai')
            ->first();

        $upload->fill([
            'jumlah_jadwal' => (int) $r->jadwal,
            'jumlah_karyawan' => (int) $r->karyawan,
            'periode_mulai' => $r->dari,
            'periode_selesai' => $r->sampai,
        ])->save();
    }
}
