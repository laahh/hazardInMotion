<?php

namespace App\Services\PetaRisiko\Drm;

use App\Services\PetaRisiko\PetaRisikoRepository;

/**
 * Snapshot Peta Risiko PJA versi DRM 2.1 (dari ClickHouse OBDS) di storage/app/peta-risiko-drm/:
 * - meta.json     : cakupan Site / Group Lokasi / PJA (KPI, matriks 5x5, tren mingguan) + daftar PJA
 * - pja/{id}.json : detail satu PJA (lokasi, aktivitas × detail lokasi + strip, bahaya, temuan, insiden, personil)
 * Penulisan atomik diwarisi dari PetaRisikoRepository.
 */
class DrmRepository extends PetaRisikoRepository
{
    protected const DIR = 'peta-risiko-drm';
}
