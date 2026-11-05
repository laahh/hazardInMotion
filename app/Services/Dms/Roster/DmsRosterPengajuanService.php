<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Models\DmsRosterPengajuan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Pengajuan perubahan roster dari Form Edit per SID: form hanya mencatat
 * pengajuan; baris jadwal baru diperbarui saat approver menyetujui dengan
 * tanda tangan (lewat DmsRosterMasterEditService::simpan, aturan & riwayatnya
 * sama dengan tabel Edit Roster). Rentang hari ini ± 7 hari berlaku saat
 * mengajukan, bukan saat disetujui.
 */
final class DmsRosterPengajuanService
{
    public function __construct(
        private readonly DmsRosterMasterEditService $edit,
    ) {}

    public static function adaTabel(): bool
    {
        try {
            return Schema::hasTable('dms_roster_pengajuan');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Tanggal SID ini yang masih menunggu approval.
     *
     * @return array<string, string> tanggal => kode yang diajukan
     */
    public function menunggu(string $sid): array
    {
        if (! self::adaTabel()) {
            return [];
        }
        $out = [];
        foreach (DmsRosterPengajuan::query()->where('kode_sid', $sid)->where('status', DmsRosterPengajuan::STATUS_PENDING)->get(['perubahan']) as $p) {
            foreach ($p->perubahan as $u) {
                $out[$u['tanggal']] = $u['baru'];
            }
        }

        return $out;
    }

    /**
     * Isi pengajuan — murni, tanpa DB. Tanggal yang kodenya sama dilewati.
     *
     * @param  list<array{tanggal:string,kode:string,menunggu:bool,boleh:bool}>  $hari  DmsRosterMasterEditService::hariForm()
     * @param  list<array{tanggal:string,kode:string}>  $sel
     * @param  array<string, string>  $menunggu  tanggal yang sudah diajukan
     * @return array{galat: list<string>, perubahan: list<array{tanggal:string,lama:?string,baru:string}>}
     */
    public static function rencana(array $hari, array $sel, array $menunggu): array
    {
        $kini = array_column($hari, null, 'tanggal');
        $galat = [];
        $perubahan = [];
        foreach ($sel as $s) {
            $tgl = (string) $s['tanggal'];
            $h = $kini[$tgl] ?? null;
            if ($h === null) {
                $galat[] = $tgl.': di luar rentang seminggu sebelum dan sesudah hari ini.';
            } elseif (! $h['boleh']) {
                $galat[] = $tgl.': di luar periode roster karyawan ini — upload roster untuk periode tersebut.';
            } elseif (isset($menunggu[$tgl])) {
                $galat[] = $tgl.': sudah ada pengajuan yang menunggu approval.';
            } elseif ((string) $s['kode'] !== $h['kode']) {
                $perubahan[$tgl] = ['tanggal' => $tgl, 'lama' => $h['kode'] === '' ? null : $h['kode'], 'baru' => (string) $s['kode']];
            }
        }
        if ($galat !== []) {
            return ['galat' => array_values(array_unique($galat)), 'perubahan' => []];
        }
        ksort($perubahan);

        return ['galat' => [], 'perubahan' => array_values($perubahan)];
    }

    /**
     * @param  array{sid:string,nama:?string,perusahaan:?string,jabatan:?string,site:?string,hari:list<array<string, mixed>>}  $jadwal  DmsRosterMasterEditService::jadwalSid()
     * @param  list<array{tanggal:string,kode:string}>  $sel
     * @return array{galat: list<string>, pengajuan: ?DmsRosterPengajuan}
     */
    public function ajukan(array $jadwal, array $sel, ?string $catatan, string $oleh): array
    {
        $r = self::rencana($jadwal['hari'], $sel, $this->menunggu($jadwal['sid']));
        if ($r['galat'] !== []) {
            return ['galat' => $r['galat'], 'pengajuan' => null];
        }
        if ($r['perubahan'] === []) {
            return ['galat' => ['Tidak ada perubahan kode untuk diajukan.'], 'pengajuan' => null];
        }

        return ['galat' => [], 'pengajuan' => DmsRosterPengajuan::create([
            'kode_sid' => $jadwal['sid'],
            'nama' => $jadwal['nama'],
            'perusahaan' => $jadwal['perusahaan'],
            'jabatan' => $jadwal['jabatan'],
            'site' => $jadwal['site'],
            'perubahan' => $r['perubahan'],
            'status' => DmsRosterPengajuan::STATUS_PENDING,
            'catatan' => $catatan,
            'diajukan_oleh' => mb_substr($oleh, 0, 150),
        ])];
    }

    /**
     * Setujui (jadwal diperbarui, sekaligus status — gagal = tidak ada yang
     * berubah) atau tolak pengajuan yang masih PENDING.
     *
     * @param  array{path:string,sumber:string}|null  $ttd  Hasil simpanTtdDi(); wajib untuk persetujuan.
     */
    public function review(DmsRosterPengajuan $pengajuan, bool $setuju, ?string $catatan, string $oleh, ?array $ttd = null): void
    {
        if ($setuju && $ttd === null) {
            throw new RuntimeException('Persetujuan wajib disertai tanda tangan.');
        }

        DB::transaction(function () use ($pengajuan, $setuju, $catatan, $oleh, $ttd): void {
            $kini = DmsRosterPengajuan::query()->whereKey($pengajuan->getKey())->lockForUpdate()->firstOrFail();
            if (! $kini->isPending()) {
                throw new RuntimeException('Pengajuan ini sudah direview ('.$kini->status.').');
            }

            if ($setuju) {
                $sid = (string) $kini->kode_sid;
                $hasil = $this->edit->simpan(
                    array_map(static fn (array $u): array => ['sid' => $sid, 'tanggal' => $u['tanggal'], 'kode' => $u['baru']], $kini->perubahan),
                    [],
                    null,
                    $oleh.' (pengajuan #'.$kini->getKey().')',
                );
                if ($hasil['galat'] !== []) {
                    throw new RuntimeException('Jadwal tidak bisa diperbarui: '.implode(' ', $hasil['galat']));
                }
            }

            $kini->update([
                'status' => $setuju ? DmsRosterPengajuan::STATUS_APPROVED : DmsRosterPengajuan::STATUS_REJECTED,
                'direview_oleh' => mb_substr($oleh, 0, 150),
                'direview_pada' => CarbonImmutable::now(),
                'catatan_review' => $catatan,
                'ttd_path' => $ttd['path'] ?? null,
                'ttd_sumber' => $ttd['sumber'] ?? null,
            ]);
        });
    }
}
