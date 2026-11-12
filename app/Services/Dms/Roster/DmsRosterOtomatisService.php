<?php

declare(strict_types=1);

namespace App\Services\Dms\Roster;

use App\Models\DmsRosterMasterUpload;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Jadwal otomatis Master Roster untuk perusahaan di config
 * dms_roster.master.otomatis (PT Berau Coal & PT Berau Coal Energy).
 *
 * Karyawan aktif (cache Tahap 8) yang tanggalnya belum tertutup roster upload
 * berstatus PENDING/APPROVED dibuatkan jadwal Senin–Jumat D1–D5 jam kantor,
 * Sabtu/Minggu OFF. Disimpan sebagai upload APPROVED biasa, satu per PT per
 * bulan, dengan diupload_oleh = penanda — sehingga semua pembaca jadwal yang
 * ada (jadwalBerlaku, Edit Roster, Scorecard, cakupan) ikut membacanya. Setiap
 * run menulis ulang upload otomatis PT/bulan itu (idempoten).
 */
final class DmsRosterOtomatisService
{
    private const BATCH = 1000;

    public function __construct(private readonly DmsRosterKaryawanAktif $aktif)
    {
    }

    public static function penanda(): string
    {
        return (string) config('dms_roster.master.otomatis.penanda', 'SISTEM:OTOMATIS');
    }

    /** @return list<string> */
    public static function perusahaan(): array
    {
        return array_values(array_filter(array_map('trim', (array) config('dms_roster.master.otomatis.perusahaan', []))));
    }

    public static function judul(string $perusahaan, CarbonImmutable $bulan): string
    {
        return mb_substr('Jadwal otomatis '.$perusahaan.' '.$bulan->locale('id')->translatedFormat('F Y'), 0, 150);
    }

    /**
     * Baris jadwal (tanpa upload_id) untuk satu bulan — fungsi murni.
     *
     * @param  list<array{sid:string,nama?:string,perusahaan?:string,jabatan?:string,site?:string}>  $karyawan
     * @param  array<string, true>  $sudahAda  "SID-HURUF-BESAR|Y-m-d" yang sudah punya jadwal dari roster upload
     * @param  array{jam_awal?:string,jam_akhir?:string,kode_hari?:array<int,string>}  $aturan
     * @return list<array{kode_sid:string,nama:?string,perusahaan:?string,jabatan:?string,site:?string,tanggal:string,kode:string,jam_masuk:?string,jam_keluar:?string,menit_kerja:?int,menit_total_kerja:?int}>
     */
    public static function rencana(array $karyawan, CarbonImmutable $bulan, array $sudahAda, array $aturan): array
    {
        $awal = $aturan['jam_awal'] ?? '07:30';
        $akhir = $aturan['jam_akhir'] ?? '16:30';
        $kodeHari = $aturan['kode_hari'] ?? [1 => 'D1', 2 => 'D2', 3 => 'D3', 4 => 'D4', 5 => 'D5'];
        $menit = self::selisihMenit($awal, $akhir);

        $hari = [];
        $mulai = $bulan->startOfMonth();
        for ($t = $mulai; $t->month === $mulai->month; $t = $t->addDay()) {
            $kode = $kodeHari[$t->dayOfWeekIso] ?? 'OFF';
            $hari[$t->toDateString()] = $kode;
        }

        $baris = [];
        $sudahDiproses = [];
        foreach ($karyawan as $k) {
            $sid = trim((string) ($k['sid'] ?? ''));
            $kunci = mb_strtoupper($sid);
            if ($sid === '' || isset($sudahDiproses[$kunci])) {
                continue;
            }
            $sudahDiproses[$kunci] = true;

            foreach ($hari as $tanggal => $kode) {
                if (isset($sudahAda[$kunci.'|'.$tanggal])) {
                    continue;
                }
                $kerja = $kode !== 'OFF';
                $baris[] = [
                    'kode_sid' => $sid,
                    'nama' => self::potong($k['nama'] ?? null, 150),
                    'perusahaan' => self::potong($k['perusahaan'] ?? null, 255),
                    'jabatan' => self::potong($k['jabatan'] ?? null, 150),
                    'site' => self::potong($k['site'] ?? null, 60),
                    'tanggal' => $tanggal,
                    'kode' => $kode,
                    'jam_masuk' => $kerja ? $awal : null,
                    'jam_keluar' => $kerja ? $akhir : null,
                    'menit_kerja' => $kerja ? $menit : null,
                    'menit_total_kerja' => $kerja ? $menit : null,
                ];
            }
        }

        return $baris;
    }

    /**
     * Tulis ulang jadwal otomatis satu PT untuk satu bulan.
     *
     * @return array{perusahaan:string,bulan:string,status:string,karyawan:int,baris:int,upload_id:?int}
     */
    public function generate(string $perusahaan, CarbonImmutable $bulan): array
    {
        $bulan = $bulan->startOfMonth();
        $hasil = ['perusahaan' => $perusahaan, 'bulan' => $bulan->format('Y-m'), 'status' => 'ok', 'karyawan' => 0, 'baris' => 0, 'upload_id' => null];

        $karyawan = $this->aktif->untukPerusahaan($perusahaan);
        if ($karyawan === null) {
            // Tanpa daftar karyawan aktif, jangan sentuh jadwal otomatis yang sudah ada.
            return ['status' => 'tanpa_cache'] + $hasil;
        }

        $sids = array_values(array_unique(array_filter(array_map(
            static fn (array $k): string => mb_strtoupper(trim((string) $k['sid'])),
            $karyawan
        ))));
        $baris = self::rencana($karyawan, $bulan, $this->sudahAda($sids, $bulan), (array) config('dms_roster.master.otomatis', []));

        $hasil['baris'] = count($baris);
        $hasil['karyawan'] = count(array_unique(array_map(static fn (array $b): string => mb_strtoupper($b['kode_sid']), $baris)));
        $hasil['upload_id'] = $this->tulis($perusahaan, $bulan, $baris, $hasil['karyawan']);

        return $hasil;
    }

    /**
     * (SID, tanggal) yang sudah punya jadwal dari roster upload (bukan otomatis), PENDING/APPROVED.
     *
     * @param  list<string>  $sids  huruf besar
     * @return array<string, true>
     */
    private function sudahAda(array $sids, CarbonImmutable $bulan): array
    {
        $ada = [];
        foreach (array_chunk($sids, self::BATCH) as $potongan) {
            $rows = DB::table('dms_roster_master_jadwal as j')
                ->join('dms_roster_master_upload as u', 'u.id', '=', 'j.upload_id')
                ->whereIn('u.status', [DmsRosterMasterUpload::STATUS_PENDING, DmsRosterMasterUpload::STATUS_APPROVED])
                ->where('u.diupload_oleh', '<>', self::penanda())
                ->whereBetween('j.tanggal', [$bulan->startOfMonth()->toDateString(), $bulan->endOfMonth()->toDateString()])
                ->whereIn(DB::raw('UPPER(TRIM(j.kode_sid))'), $potongan)
                ->selectRaw('UPPER(TRIM(j.kode_sid)) AS sid, j.tanggal')
                ->get();
            foreach ($rows as $r) {
                $ada[$r->sid.'|'.CarbonImmutable::parse((string) $r->tanggal)->toDateString()] = true;
            }
        }

        return $ada;
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     */
    private function tulis(string $perusahaan, CarbonImmutable $bulan, array $baris, int $jumlahKaryawan): ?int
    {
        $adaTotal = DmsRosterMasterService::adaKolomTotal();
        $judul = self::judul($perusahaan, $bulan);

        return DB::transaction(function () use ($perusahaan, $bulan, $baris, $jumlahKaryawan, $adaTotal, $judul): ?int {
            $upload = DmsRosterMasterUpload::query()
                ->where('diupload_oleh', self::penanda())
                ->where('judul', $judul)
                ->lockForUpdate()
                ->first();

            if ($upload !== null) {
                DB::table('dms_roster_master_jadwal')->where('upload_id', $upload->id)->delete();
            }
            if ($baris === []) {
                $upload?->delete();

                return null;
            }

            $data = [
                'judul' => $judul,
                'periode_mulai' => $bulan->startOfMonth()->toDateString(),
                'periode_selesai' => $bulan->endOfMonth()->toDateString(),
                'nama_file' => 'Dibuat otomatis oleh sistem',
                'path_file' => '',
                'jumlah_karyawan' => $jumlahKaryawan,
                'jumlah_jadwal' => count($baris),
                'status' => DmsRosterMasterUpload::STATUS_APPROVED,
                'catatan' => 'Jadwal otomatis '.$perusahaan.': Senin–Jumat D1–D5, Sabtu/Minggu OFF, untuk karyawan yang belum punya roster.',
                'diupload_oleh' => self::penanda(),
                'direview_oleh' => 'Otomatis (sistem)',
                'direview_pada' => now(),
            ];
            $upload === null ? $upload = DmsRosterMasterUpload::create($data) : $upload->update($data);

            foreach (array_chunk($baris, self::BATCH) as $potongan) {
                DB::table('dms_roster_master_jadwal')->insert(array_map(
                    static function (array $b) use ($upload, $adaTotal): array {
                        $b['upload_id'] = $upload->id;
                        if (! $adaTotal) {
                            unset($b['menit_total_kerja']);
                        }

                        return $b;
                    },
                    $potongan
                ));
            }

            return (int) $upload->id;
        });
    }

    private static function selisihMenit(string $awal, string $akhir): int
    {
        [$h1, $m1] = array_map('intval', explode(':', $awal) + [1 => 0]);
        [$h2, $m2] = array_map('intval', explode(':', $akhir) + [1 => 0]);
        $selisih = ($h2 * 60 + $m2) - ($h1 * 60 + $m1);

        return $selisih <= 0 ? $selisih + 1440 : $selisih;
    }

    private static function potong(mixed $nilai, int $panjang): ?string
    {
        $nilai = trim((string) ($nilai ?? ''));

        return $nilai === '' ? null : mb_substr($nilai, 0, $panjang);
    }
}
