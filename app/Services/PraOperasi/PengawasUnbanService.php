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
 * Pengajuan unbanned pengawas: master dari validasi_status_banned_pengawas
 * (satu baris per tanggal_kerja + sid), hasil ke pengawas_unban_requests.
 * Tanggal yang pengajuannya masih pending/approved tidak bisa diajukan lagi;
 * yang ditolak boleh diajukan ulang.
 */
final class PengawasUnbanService
{
    private const TABEL_MASTER = 'validasi_status_banned_pengawas';

    private const TABEL_PENGAJUAN = 'pengawas_unban_requests';

    /** Master saat ini hanya berisi 'BANNED SAP'; awalan ini menyaring status lain bila kelak ada. */
    private const AWALAN_STATUS_BANNED = 'BANNED';

    private const MAKS_RIWAYAT = 50;

    public function __construct(
        private readonly FonnteService $fonnte,
    ) {}

    /**
     * @return array{found: bool, can_submit: bool, message: string, data?: array<string, string>, banned_log_options?: list<array{value: string, label: string}>}
     */
    public function cariSid(string $sid): array
    {
        $sid = self::rapikanSid($sid);
        if ($sid === '') {
            return ['found' => false, 'can_submit' => false, 'message' => 'Ketik SID Anda lalu tekan Cari.'];
        }

        $baris = $this->dasarMaster($sid)
            ->orderByDesc('tanggal_kerja')
            ->limit(self::MAKS_RIWAYAT)
            ->get();

        if ($baris->isEmpty()) {
            return [
                'found' => false,
                'can_submit' => false,
                'message' => 'SID tidak ditemukan di data banned pengawas. Periksa ejaan SID atau hubungi admin.',
            ];
        }

        $terpakai = $this->tanggalTerpakai($sid);
        $pilihan = [];
        foreach ($baris as $r) {
            $tanggal = CarbonImmutable::parse((string) $r->tanggal_kerja);
            if (! isset($terpakai[$tanggal->toDateString()])) {
                $pilihan[] = [
                    'value' => $tanggal->toDateString(),
                    'label' => sprintf('%s — %s (%s)', $tanggal->format('d M Y'), self::teks($r->alasan_banned) ?: '—', self::teks($r->site) ?: '—'),
                ];
            }
        }

        $terbaru = $baris->first();

        return [
            'found' => true,
            'can_submit' => $pilihan !== [],
            'message' => $pilihan !== []
                ? 'Data ditemukan. Lanjut isi formulir di bawah.'
                : 'Semua tanggal banned untuk SID ini sudah pernah diajukan.',
            'data' => [
                'karyawan' => self::teks($terbaru->nama),
                'perusahaan' => self::teks($terbaru->perusahaan),
                'site_dedicated' => self::teks($terbaru->site),
                'banned_reason' => self::teks($terbaru->alasan_banned),
            ],
            'banned_log_options' => $pilihan,
        ];
    }

    /**
     * @throws ValidationException
     */
    public function ajukan(string $sid, string $tanggalKerja, string $noHp, string $alasan, UploadedFile $file): PengawasUnbanRequest
    {
        if (! Schema::hasTable(self::TABEL_PENGAJUAN)) {
            throw ValidationException::withMessages([
                'sid' => ['Form belum siap menerima pengajuan. Silakan hubungi admin.'],
            ]);
        }

        $sid = self::rapikanSid($sid);
        $tanggal = CarbonImmutable::parse($tanggalKerja)->startOfDay();
        $path = null;

        try {
            return DB::transaction(function () use ($sid, $tanggal, $noHp, $alasan, $file, &$path): PengawasUnbanRequest {
                // Kunci baris master: dua kiriman bersamaan untuk tanggal yang sama antre di sini.
                $master = $this->dasarMaster($sid)
                    ->whereDate('tanggal_kerja', $tanggal->toDateString())
                    ->lockForUpdate()
                    ->first();

                if ($master === null) {
                    throw ValidationException::withMessages([
                        'tanggal_kerja' => ['Riwayat banned tidak ditemukan untuk SID ini. Cari ulang SID Anda.'],
                    ]);
                }
                if (isset($this->tanggalTerpakai($sid)[$tanggal->toDateString()])) {
                    throw ValidationException::withMessages([
                        'tanggal_kerja' => ['Tanggal banned ini sudah pernah diajukan.'],
                    ]);
                }

                $path = $this->simpanBukti($file, $sid, $tanggal);
                $nama = self::teks($master->nama);

                return PengawasUnbanRequest::query()->create([
                    'tanggal_kerja' => $tanggal->toDateString(),
                    'sid' => $sid,
                    'nik' => self::teks($master->nik) ?: null,
                    'karyawan' => $nama ?: null,
                    'perusahaan' => self::teks($master->perusahaan) ?: null,
                    'site_dedicated' => self::teks($master->site) ?: null,
                    'banned_reason' => self::teks($master->alasan_banned) ?: null,
                    'status_banned_ref' => self::teks($master->status_banned) ?: null,
                    'alasan_pengajuan' => trim($alasan),
                    'evidence_file_path' => $path,
                    'evidence_original_name' => $file->getClientOriginalName(),
                    'evidence_mime' => $file->getMimeType(),
                    'evidence_uploaded_at' => now(),
                    'status' => AutoBannedUnbanStatus::Pending,
                    'week' => $tanggal->format('\WW'),
                    'iso_year' => $tanggal->format('o'),
                    'submitted_by_name' => $nama ?: 'Form Publik',
                    'no_hp' => $this->fonnte->normalizePhoneNumber($noHp) ?: null,
                ]);
            });
        } catch (Throwable $e) {
            // Transaksi gagal setelah file tersimpan: jangan tinggalkan file yatim.
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }

            throw $e;
        }
    }

    private function dasarMaster(string $sid): Builder
    {
        return DB::table(self::TABEL_MASTER)
            ->where('sid', $sid)
            ->where('status_banned', 'like', self::AWALAN_STATUS_BANNED.'%');
    }

    /**
     * @return array<string, true> tanggal Y-m-d yang pengajuannya masih pending/approved
     */
    private function tanggalTerpakai(string $sid): array
    {
        if (! Schema::hasTable(self::TABEL_PENGAJUAN)) {
            return [];
        }

        $out = [];
        foreach (PengawasUnbanRequest::query()
            ->where('sid', $sid)
            ->whereIn('status', [AutoBannedUnbanStatus::Pending, AutoBannedUnbanStatus::Approved])
            ->pluck('tanggal_kerja') as $tanggal) {
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
