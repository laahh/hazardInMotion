<?php

declare(strict_types=1);

namespace App\Services\PraOperasi;

use App\Enums\AutoBannedUnbanStatus;
use App\Models\PengawasUnbanRequest;
use App\Services\FonnteService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Pengajuan unbanned pengawas dari DUA sumber:
 * - harian   : validasi_status_banned_pengawas (satu baris per tanggal_kerja + sid)
 * - mingguan : validasi_status_banned_pengawas_weekly (satu baris per minggu + sid), lihat PengawasBannedMingguanSource
 * Hasil ke pengawas_unban_requests (kolom `sumber` membedakan keduanya).
 *
 * Nilai pilihan di form: harian = "Y-m-d" (tanggal kerja), mingguan = "mingguan|Y-m-d" (Senin awal minggu).
 * Tanggal/minggu yang pengajuannya masih pending/approved tidak bisa diajukan lagi; yang ditolak boleh ulang.
 * Pilihan mingguan baru muncul setelah kolom `sumber` dimigrasi (tanpa itu harian & mingguan bisa tertukar).
 */
final class PengawasUnbanService
{
    public const SUMBER_HARIAN = 'harian';

    public const SUMBER_MINGGUAN = 'mingguan';

    private const TABEL_MASTER = 'validasi_status_banned_pengawas';

    private const TABEL_PENGAJUAN = 'pengawas_unban_requests';

    /** Master saat ini hanya berisi 'BANNED SAP'; awalan ini menyaring status lain bila kelak ada. */
    private const AWALAN_STATUS_BANNED = 'BANNED';

    private const MAKS_RIWAYAT = 50;

    private ?bool $adaSumber = null;

    public function __construct(
        private readonly FonnteService $fonnte,
        private readonly PengawasBannedMingguanSource $mingguan,
    ) {}

    /**
     * Pecah nilai pilihan form menjadi [sumber, tanggal]. "2026-11-06" → harian; "mingguan|2026-11-02" → mingguan.
     *
     * @return array{0: string, 1: string}
     */
    public static function pecahPilihan(string $nilai): array
    {
        return str_starts_with($nilai, self::SUMBER_MINGGUAN.'|')
            ? [self::SUMBER_MINGGUAN, substr($nilai, strlen(self::SUMBER_MINGGUAN) + 1)]
            : [self::SUMBER_HARIAN, $nilai];
    }

    /**
     * @return array{found: bool, can_submit: bool, message: string, data?: array<string, string>, banned_log_options?: list<array{value: string, label: string}>}
     */
    public function cariSid(string $sid): array
    {
        $sid = self::rapikanSid($sid);
        if ($sid === '') {
            return ['found' => false, 'can_submit' => false, 'message' => 'Ketik SID Anda lalu tekan Cari.'];
        }

        $riwayat = $this->riwayatGabungan($sid);
        if ($riwayat === []) {
            return [
                'found' => false,
                'can_submit' => false,
                'message' => 'SID tidak ditemukan di data banned pengawas (harian maupun mingguan). Periksa ejaan SID atau hubungi admin.',
            ];
        }

        $terpakai = [
            self::SUMBER_HARIAN => $this->tanggalTerpakai($sid, self::SUMBER_HARIAN),
            self::SUMBER_MINGGUAN => $this->tanggalTerpakai($sid, self::SUMBER_MINGGUAN),
        ];
        $pilihan = [];
        foreach ($riwayat as $r) {
            if (! isset($terpakai[$r['sumber']][$r['tanggal']->toDateString()])) {
                $pilihan[] = ['value' => $r['nilai'], 'label' => $r['label']];
            }
        }
        $terbaru = $riwayat[0];

        return [
            'found' => true,
            'can_submit' => $pilihan !== [],
            'message' => $pilihan !== []
                ? 'Data ditemukan. Lanjut isi formulir di bawah.'
                : 'Semua riwayat banned untuk SID ini sudah pernah diajukan.',
            'data' => [
                'karyawan' => $terbaru['nama'],
                'perusahaan' => $terbaru['perusahaan'],
                'site_dedicated' => $terbaru['site'],
                'banned_reason' => $terbaru['alasan'],
            ],
            'banned_log_options' => $pilihan,
        ];
    }

    /**
     * @throws ValidationException
     */
    public function ajukan(string $sid, string $pilihan, string $noHp, string $alasan, UploadedFile $file): PengawasUnbanRequest
    {
        if (! Schema::hasTable(self::TABEL_PENGAJUAN)) {
            throw ValidationException::withMessages([
                'sid' => ['Form belum siap menerima pengajuan. Silakan hubungi admin.'],
            ]);
        }

        [$sumber, $tanggalTeks] = self::pecahPilihan($pilihan);
        if ($sumber === self::SUMBER_MINGGUAN && ! $this->mingguanAktif()) {
            throw ValidationException::withMessages([
                'tanggal_kerja' => ['Pengajuan banned mingguan belum bisa diterima. Silakan hubungi admin.'],
            ]);
        }

        $sid = self::rapikanSid($sid);
        try {
            $tanggal = CarbonImmutable::parse($tanggalTeks)->startOfDay();
        } catch (Throwable) {
            throw ValidationException::withMessages(['tanggal_kerja' => ['Riwayat banned tidak valid. Cari ulang SID Anda.']]);
        }
        $path = null;

        try {
            return DB::transaction(function () use ($sid, $sumber, $tanggal, $noHp, $alasan, $file, &$path): PengawasUnbanRequest {
                // Kunci baris master: dua kiriman bersamaan untuk tanggal/minggu yang sama antre di sini.
                $master = $sumber === self::SUMBER_MINGGUAN
                    ? $this->mingguan->satuDikunci($sid, $tanggal)
                    : $this->masterHarianDikunci($sid, $tanggal);

                if ($master === null) {
                    throw ValidationException::withMessages([
                        'tanggal_kerja' => ['Riwayat banned tidak ditemukan untuk SID ini. Cari ulang SID Anda.'],
                    ]);
                }
                $kunci = $sumber === self::SUMBER_MINGGUAN ? $master['mulai'] : $tanggal;
                if (isset($this->tanggalTerpakai($sid, $sumber)[$kunci->toDateString()])) {
                    throw ValidationException::withMessages([
                        'tanggal_kerja' => [$sumber === self::SUMBER_MINGGUAN ? 'Minggu banned ini sudah pernah diajukan.' : 'Tanggal banned ini sudah pernah diajukan.'],
                    ]);
                }

                $path = $this->simpanBukti($file, $sid, $kunci);
                $data = [
                    'tanggal_kerja' => $kunci->toDateString(),
                    'sid' => $sid,
                    'nik' => $master['nik'] ?: null,
                    'karyawan' => $master['nama'] ?: null,
                    'perusahaan' => $master['perusahaan'] ?: null,
                    'site_dedicated' => $master['site'] ?: null,
                    'banned_reason' => $master['alasan'] ?: null,
                    'status_banned_ref' => $master['status'] ?: null,
                    'alasan_pengajuan' => trim($alasan),
                    'evidence_file_path' => $path,
                    'evidence_original_name' => $file->getClientOriginalName(),
                    'evidence_mime' => $file->getMimeType(),
                    'evidence_uploaded_at' => now(),
                    'status' => AutoBannedUnbanStatus::Pending,
                    'week' => $kunci->format('\WW'),
                    'iso_year' => $kunci->format('o'),
                    'submitted_by_name' => $master['nama'] ?: 'Form Publik',
                    'no_hp' => $this->fonnte->normalizePhoneNumber($noHp) ?: null,
                ];
                if ($this->adaKolomSumber()) {
                    $data['sumber'] = $sumber;
                }

                return PengawasUnbanRequest::query()->create($data);
            });
        } catch (Throwable $e) {
            // Transaksi gagal setelah file tersimpan: jangan tinggalkan file yatim.
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }

            throw $e;
        }
    }

    /**
     * Riwayat banned harian + mingguan, terbaru dulu, dalam satu bentuk.
     *
     * @return list<array{sumber: string, tanggal: CarbonImmutable, nilai: string, label: string, nama: string, perusahaan: string, site: string, alasan: string}>
     */
    private function riwayatGabungan(string $sid): array
    {
        $hasil = [];
        foreach ($this->dasarMaster($sid)->orderByDesc('tanggal_kerja')->limit(self::MAKS_RIWAYAT)->get() as $r) {
            $tanggal = CarbonImmutable::parse((string) $r->tanggal_kerja)->startOfDay();
            $hasil[] = [
                'sumber' => self::SUMBER_HARIAN, 'tanggal' => $tanggal, 'nilai' => $tanggal->toDateString(),
                'label' => sprintf('%s — Harian — %s (%s)', $tanggal->format('d M Y'), self::teks($r->alasan_banned) ?: '—', self::teks($r->site) ?: '—'),
                'nama' => self::teks($r->nama), 'perusahaan' => self::teks($r->perusahaan), 'site' => self::teks($r->site), 'alasan' => self::teks($r->alasan_banned),
            ];
        }
        if ($this->mingguanAktif()) {
            foreach ($this->mingguan->riwayat($sid, self::MAKS_RIWAYAT) as $m) {
                $hasil[] = [
                    'sumber' => self::SUMBER_MINGGUAN, 'tanggal' => $m['mulai'], 'nilai' => self::SUMBER_MINGGUAN.'|'.$m['mulai']->toDateString(),
                    'label' => sprintf('%s %s (%s – %s) — Mingguan — %s (%s)', $m['mulai']->format('\WW'), $m['mulai']->format('o'),
                        $m['mulai']->format('d M'), $m['selesai']->format('d M Y'), $m['alasan'] ?: '—', $m['site'] ?: '—'),
                    'nama' => $m['nama'], 'perusahaan' => $m['perusahaan'], 'site' => $m['site'], 'alasan' => $m['alasan'],
                ];
            }
        }
        usort($hasil, fn ($a, $b) => $b['tanggal'] <=> $a['tanggal'] ?: strcmp($a['sumber'], $b['sumber']));

        return array_slice($hasil, 0, self::MAKS_RIWAYAT);
    }

    /**
     * @return array{nama: string, nik: string, perusahaan: string, site: string, alasan: string, status: string}|null
     */
    private function masterHarianDikunci(string $sid, CarbonImmutable $tanggal): ?array
    {
        $r = $this->dasarMaster($sid)->whereDate('tanggal_kerja', $tanggal->toDateString())->lockForUpdate()->first();

        return $r === null ? null : [
            'nama' => self::teks($r->nama), 'nik' => self::teks($r->nik), 'perusahaan' => self::teks($r->perusahaan),
            'site' => self::teks($r->site), 'alasan' => self::teks($r->alasan_banned), 'status' => self::teks($r->status_banned),
        ];
    }

    private function dasarMaster(string $sid): Builder
    {
        return DB::table(self::TABEL_MASTER)
            ->where('sid', $sid)
            ->where('status_banned', 'like', self::AWALAN_STATUS_BANNED.'%');
    }

    /** Mingguan hanya aktif bila tabelnya terbaca DAN kolom `sumber` sudah ada di tabel pengajuan. */
    private function mingguanAktif(): bool
    {
        return $this->adaKolomSumber() && $this->mingguan->tersedia();
    }

    private function adaKolomSumber(): bool
    {
        return $this->adaSumber ??= Schema::hasTable(self::TABEL_PENGAJUAN) && Schema::hasColumn(self::TABEL_PENGAJUAN, 'sumber');
    }

    /**
     * @return array<string, true> tanggal Y-m-d (harian) / Senin awal minggu (mingguan) yang masih pending/approved
     */
    private function tanggalTerpakai(string $sid, string $sumber): array
    {
        if (! Schema::hasTable(self::TABEL_PENGAJUAN)) {
            return [];
        }

        $q = PengawasUnbanRequest::query()
            ->where('sid', $sid)
            ->whereIn('status', [AutoBannedUnbanStatus::Pending, AutoBannedUnbanStatus::Approved]);
        if ($this->adaKolomSumber()) {
            $q->where('sumber', $sumber);
        } elseif ($sumber === self::SUMBER_MINGGUAN) {
            return [];
        }

        $out = [];
        foreach ($q->pluck('tanggal_kerja') as $tanggal) {
            $out[$tanggal->toDateString()] = true;
        }

        return $out;
    }

    private function simpanBukti(UploadedFile $file, string $sid, CarbonImmutable $tanggal): string
    {
        $folder = 'pengawas-unban/evidence/'.$tanggal->format('o').'/'.$tanggal->format('\WW');
        // Ekstensi dari isi file dulu, bukan dari nama kiriman pengguna.
        $ekstensi = preg_replace('/[^a-z0-9]+/', '', strtolower($file->extension() ?: $file->getClientOriginalExtension())) ?: 'bin';
        $namaAman = preg_replace('/[^A-Z0-9]+/', '', $sid) ?: 'bukti';

        $path = $file->storeAs($folder, $namaAman.'_'.$tanggal->format('Ymd').'_'.time().'.'.$ekstensi, 'local');

        if ($path === false || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages([
                'evidence_file' => ['Gagal menyimpan file ke server. Coba lagi atau hubungi admin.'],
            ]);
        }

        return $path;
    }

    private static function rapikanSid(string $sid): string
    {
        return strtoupper(trim($sid));
    }

    private static function teks(mixed $nilai): string
    {
        return trim((string) ($nilai ?? ''));
    }
}
