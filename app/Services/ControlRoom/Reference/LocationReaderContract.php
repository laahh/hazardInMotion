<?php

declare(strict_types=1);

namespace App\Services\ControlRoom\Reference;

use App\Enums\ControlRoomSiteCode;
use Illuminate\Support\Collection;

interface LocationReaderContract
{
    /**
     * @return Collection<int, array{site: string, lokasi: string, detail_lokasi: string}>
     */
    public function all(ControlRoomSiteCode $site): Collection;

    /**
     * @return array{site: string, lokasi: string, detail_lokasi: string}|null
     */
    public function find(string $lokasi, string $detilLokasi): ?array;

    public function isCritical(string $lokasi, string $detilLokasi): bool;

    /**
     * @return Collection<int, array{site: string, lokasi: string, detail_lokasi: string}>
     */
    public function criticalAreas(ControlRoomSiteCode $site): Collection;
}
