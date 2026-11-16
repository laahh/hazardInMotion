<?php

namespace App\Console\Commands;

use App\Services\PetaRisiko\Drm\DrmMappingEkspor;
use App\Services\PetaRisiko\Drm\DrmMaster;
use App\Services\PetaRisiko\Drm\DrmRepository;
use App\Services\PetaRisiko\Drm\DrmSnapshotBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class PetaRisikoSyncCommand extends Command
{
    protected $signature = 'peta-risiko:sync
        {--sampai= : Tanggal akhir (YYYY-MM-DD), bawaan kemarin WITA}
        {--cek : Hanya periksa semua query (EXPLAIN) tanpa menghitung/menyimpan}';

    protected $description = 'Hitung ulang Peta Risiko PJA (DRM 2.1) dari ClickHouse OBDS dengan baseline master mapping (peta-risiko:impor-master)';

    public function handle(DrmSnapshotBuilder $builder, DrmRepository $repo, DrmMappingEkspor $ekspor): int
    {
        ini_set('memory_limit', '2048M');
        $mulai = microtime(true);

        try {
            $sampai = $this->option('sampai')
                ? CarbonImmutable::createFromFormat('Y-m-d', (string) $this->option('sampai'))->startOfDay()
                : CarbonImmutable::now('Asia/Makassar')->subDay()->startOfDay();
        } catch (Throwable) {
            $this->error('Format --sampai harus YYYY-MM-DD.');

            return self::INVALID;
        }

        if ($this->option('cek')) {
            return $this->cek($builder, $sampai);
        }

        $this->info('Peta Risiko PJA: hitung sampai '.$sampai->toDateString());
        try {
            $snapshot = $builder->denganLog(fn (string $baris) => $this->line('  '.$baris))->bangun($sampai);
            $repo->simpan($snapshot);
        } catch (Throwable $e) {
            report($e);
            $this->error('Gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        // Excel mapping ditulis setelah snapshot tersimpan; gagal di sini tidak membatalkan snapshot.
        try {
            $mulaiExcel = microtime(true);
            $ekspor->tulis($snapshot['ekspor'], DrmMaster::muat(), $sampai, (array) config('peta_risiko_drm'), $repo->pathEkspor());
            @chmod($repo->pathEkspor(), 0644);
            $this->line(sprintf('  %-45s %7s baris  %5.1f dtk', 'Excel mapping master (M01–M16)', number_format(count($snapshot['ekspor']['baris']), 0, ',', '.'), microtime(true) - $mulaiExcel));
        } catch (Throwable $e) {
            report($e);
            $this->warn('Snapshot tersimpan, tetapi Excel mapping gagal ditulis: '.$e->getMessage());
        }

        $semua = $snapshot['cakupan']['SITE|ALL']['kpi'] ?? [];
        $this->info(sprintf(
            'Selesai: %d PJA, %s bahaya unik (terkontrol %s, ada gap %s, tidak teridentifikasi %s) — %.1f dtk.',
            count($snapshot['pja']),
            number_format((int) ($semua['total'] ?? 0), 0, ',', '.'),
            number_format((int) ($semua['K'] ?? 0), 0, ',', '.'),
            number_format((int) ($semua['G'] ?? 0), 0, ',', '.'),
            number_format((int) ($semua['T'] ?? 0), 0, ',', '.'),
            microtime(true) - $mulai,
        ));

        return self::SUCCESS;
    }

    private function cek(DrmSnapshotBuilder $builder, CarbonImmutable $sampai): int
    {
        $this->info('Periksa query Peta Risiko (EXPLAIN, tanpa membaca data)…');
        try {
            $hasil = $builder->cek($sampai);
        } catch (Throwable $e) {
            $this->error('Tidak bisa terhubung ke ClickHouse: '.$e->getMessage());

            return self::FAILURE;
        }

        foreach ($hasil as $label => $error) {
            $error === null
                ? $this->line("  <info>OK</info>    {$label}")
                : $this->line("  <error>GAGAL</error> {$label}\n        ".mb_substr($error, 0, 600));
        }
        $gagal = count(array_filter($hasil));
        $gagal === 0 ? $this->info('Semua query lolos.') : $this->error("{$gagal} query gagal.");

        return $gagal === 0 ? self::SUCCESS : self::FAILURE;
    }
}
