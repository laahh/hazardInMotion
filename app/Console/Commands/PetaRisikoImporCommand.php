<?php

namespace App\Console\Commands;

use App\Services\PetaRisiko\PetaRisikoRepository;
use App\Services\PetaRisiko\PetaRisikoSnapshotBuilder;
use Illuminate\Console\Command;
use Throwable;

class PetaRisikoImporCommand extends Command
{
    protected $signature = 'peta-risiko:impor
        {xlsx : Path Mapping_PJA_Proses_Lokasi_SAP.xlsx}
        {html : Path Peta_HIRA_Risiko_Dinamis_*.html}';

    protected $description = 'Impor snapshot Peta Risiko PJA (mapping PJA-proses-lokasi-SAP + bahaya/pengendalian HIRA)';

    public function handle(PetaRisikoSnapshotBuilder $builder, PetaRisikoRepository $repo): int
    {
        $mulai = microtime(true);
        try {
            $snapshot = $builder->bangun((string) $this->argument('xlsx'), (string) $this->argument('html'));
            $repo->simpan($snapshot);
        } catch (Throwable $e) {
            $this->error('Gagal impor: '.$e->getMessage());

            return self::FAILURE;
        }

        $baris = array_sum(array_map(
            fn ($p) => count($p['periode']['wk']['rows'] ?? []) + count($p['periode']['ytd']['rows'] ?? []),
            $snapshot['pja']
        ));
        $this->info(sprintf(
            'Selesai: %d PJA, %s baris tema, %s temuan SAP open (%.1f dtk).',
            count($snapshot['pja']),
            number_format($baris, 0, ',', '.'),
            number_format(array_sum(array_map(fn ($p) => count($p['open']), $snapshot['pja'])), 0, ',', '.'),
            microtime(true) - $mulai
        ));

        return self::SUCCESS;
    }
}
