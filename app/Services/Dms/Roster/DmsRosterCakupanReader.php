<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Models\DmsRosterMasterUpload;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Bahan Overview Roster yang membandingkan roster ter-upload dengan karyawan
 * aktif (view safety, salinan DmsRosterKaryawanAktif):
 *   - peta()     ejaan nama perusahaan di roster → nama resmi karyawan aktif;
 *   - cakupan()  kartu "Cakupan upload roster" + jumlah karyawan aktif yang
 *                belum ada di roster mana pun (kolom "Kurang");
 *   - ejaanUntuk() / sidRoster()  bahan modal detail "Kurang".
 * Aturan pencocokan nama ada di DmsRosterCakupanUpload. Hasil per request
 * diingat di properti supaya query yang sama tidak diulang.
 */
final class DmsRosterCakupanReader
{
    /** @var array<string, array<string, int>>|null */
    private ?array $suara = null;

    /** @var array<string, string>|null */
    private ?array $peta = null;

    /** @var list<object{pt:string,sid:string,aktif:int}>|null */
    private ?array $pasangan = null;

    public function __construct(
        private readonly DmsRosterKaryawanAktif $karyawanAktif,
        private readonly DmsRosterMasterService $master,
    ) {}

    /**
     * Ejaan nama perusahaan di roster => nama di data karyawan aktif, supaya
     * "PT PMJ" & "PT Puncak Makmur Jaya" (dan salah ketik kecil) jadi satu.
     * Kosong bila data karyawan aktif belum tersalin — pemanggil lalu memakai
     * ejaan apa adanya.
     *
     * @return array<string, string>
     */
    public function peta(): array
    {
        if ($this->peta !== null) {
            return $this->peta;
        }

        try {
            $aktif = $this->karyawanAktif->perusahaan();
            if ($aktif === null) {
                return $this->peta = [];
            }
            $ejaan = DmsRosterMasterCache::ejaan();

            return $this->peta = DmsRosterCakupanUpload::peta(array_column($aktif, 'nama'), $ejaan, $this->suaraSid());
        } catch (Throwable $e) {
            report($e);

            return $this->peta = [];
        }
    }

    /**
     * Kartu cakupan + kolom "kurang" per perusahaan. Null bila daftar
     * karyawan aktif belum pernah tersalin (Tahap 8 dms:sync-roster-rfid).
     *
     * 'kurang' = karyawan aktif perusahaan itu yang SID-nya tidak ada di
     * roster mana pun (menunggu/disetujui). Butuh salinan lengkap karyawan
     * aktif; tanpa itu dipakai perkiraan aktif − karyawan di roster
     * ('kurang_pasti' => false). Status 'sudah' yang masih kurang menjadi
     * 'sebagian' (roster disetujui, belum semua karyawan aktif).
     *
     * @return array<string, mixed>|null
     */
    public function cakupan(): ?array
    {
        try {
            $aktif = $this->karyawanAktif->perusahaan();
            if ($aktif === null) {
                return null;
            }
            $hasil = DmsRosterCakupanUpload::bandingkan($aktif, $this->master->uploadPerSite(), $this->suaraSid());
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $sidAktif = $this->karyawanAktif->sidPerPerusahaan();
        $roster = $this->sidRosterPerPerusahaan();
        $total = 0;
        foreach ($hasil['perusahaan'] as &$p) {
            $ada = $roster[$p['nama']] ?? [];
            $belum = static fn (array $sids): int => count(array_filter($sids, static fn (string $s): bool => ! isset($ada[$s])));
            $perSite = $sidAktif[$p['nama']] ?? null;
            $p['kurang_pasti'] = $sidAktif !== null && $perSite !== null;
            if ($p['kurang_pasti']) {
                $p['kurang'] = $belum(array_merge(...array_values($perSite)));
            } else {
                $p['kurang'] = max(0, $p['karyawan'] - $p['karyawan_roster']);
            }
            // Per site: karyawan aktif site itu yang belum ada di roster mana pun
            // (terjadwal di site lain tetap dianggap sudah di roster).
            foreach ($p['site'] as &$s) {
                $s['kurang'] = $p['kurang_pasti']
                    ? $belum($perSite[$s['nama']] ?? [])
                    : max(0, $s['karyawan'] - $s['roster']);
            }
            unset($s);
            $total += $p['kurang'];
            // Sudah ada roster disetujui tapi belum semua karyawan aktif terjadwal.
            if ($p['status'] === 'sudah' && $p['kurang'] > 0) {
                $p['status'] = 'sebagian';
            }
        }
        unset($p);
        $hasil['ringkas']['kurang'] = $total;
        $hasil['ringkas']['pt']['sebagian'] = count(array_filter($hasil['perusahaan'], static fn (array $p): bool => $p['status'] === 'sebagian'));
        $hasil['ringkas']['pt']['sudah'] -= $hasil['ringkas']['pt']['sebagian'];

        return $hasil;
    }

    /**
     * Semua ejaan di roster yang terpeta ke satu perusahaan aktif (termasuk
     * namanya sendiri).
     *
     * @return list<string>
     */
    public function ejaanUntuk(string $perusahaan): array
    {
        $ejaan = [$perusahaan];
        foreach ($this->peta() as $tulisan => $resmi) {
            if (DmsRosterAksesPerusahaan::samaNama($resmi, $perusahaan)) {
                $ejaan[] = (string) $tulisan;
            }
        }

        return array_values(array_unique($ejaan));
    }

    /**
     * SID (huruf besar) yang ada di roster menunggu/disetujui dengan salah
     * satu ejaan perusahaan ini.
     *
     * @param  list<string>  $ejaan
     * @return array<string, true>
     */
    public function sidRoster(array $ejaan): array
    {
        return DmsRosterMasterCache::sidRoster($ejaan);
    }

    /**
     * SID di roster menunggu/disetujui per nama perusahaan resmi.
     *
     * @return array<string, array<string, true>>
     */
    private function sidRosterPerPerusahaan(): array
    {
        $peta = [];
        foreach ($this->peta() as $tulisan => $resmi) {
            $peta[mb_strtoupper((string) $tulisan)] = $resmi;
        }

        $out = [];
        foreach ($this->pasangan() as $r) {
            if ($r->aktif) {
                $nama = $peta[mb_strtoupper((string) $r->pt)] ?? (string) $r->pt;
                $out[$nama][(string) $r->sid] = true;
            }
        }

        return $out;
    }

    /**
     * Pasangan (ejaan perusahaan, SID) di semua jadwal, sekali query per
     * request: 'aktif' = ada di roster yang tidak ditolak. Dipakai suara SID
     * dan kolom "Kurang".
     *
     * @return list<object>
     */
    private function pasangan(): array
    {
        return $this->pasangan ??= DmsRosterMasterCache::pasangan();
    }

    /**
     * Untuk ejaan singkatan di roster ("PT SMB"): perusahaan aktif pemilik
     * SID-nya beserta jumlahnya — memutuskan singkatan yang cocok ke beberapa
     * perusahaan (DmsRosterCakupanUpload).
     *
     * @return array<string, array<string, int>>
     */
    private function suaraSid(): array
    {
        if ($this->suara !== null) {
            return $this->suara;
        }
        $this->suara = [];

        $baris = collect($this->pasangan())
            ->filter(static fn (object $r): bool => preg_match(
                '/^[A-Z0-9&]{2,12}$/',
                trim((string) preg_replace('/^(PT|CV|UD|KOPERASI)\.?\s+/i', '', trim((string) $r->pt))),
            ) === 1);
        if ($baris->isEmpty()) {
            return $this->suara;
        }

        $milik = $this->karyawanAktif->perusahaanSid($baris->pluck('sid')->all());
        foreach ($baris as $r) {
            $pt = $milik[(string) $r->sid] ?? null;
            if ($pt !== null) {
                $this->suara[(string) $r->pt][$pt] = ($this->suara[(string) $r->pt][$pt] ?? 0) + 1;
            }
        }

        return $this->suara;
    }
}
