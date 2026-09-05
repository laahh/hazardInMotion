<?php

declare(strict_types=1);

namespace App\Enums;

enum ControlRoomSiteCode: string
{
    case HeadOffice = 'HO';
    case Bmo1 = 'BMO1';
    case Bmo2 = 'BMO2';
    case Bmo3 = 'BMO3';
    case Gmo = 'GMO';
    case Lmo = 'LMO';
    case Pmo = 'PMO';
    case Smo = 'SMO';
    case Marine = 'MARINE';
    case Eksplorasi = 'EKSPLORASI';
    case Jakarta = 'JAKARTA';

    public function label(): string
    {
        return (string) (config("control-room.sites.{$this->value}.name") ?? $this->value);
    }

    /**
     * Nilai kolom `site` di sumber data (mv_ dkk / bcbeats) untuk site ini.
     * Lihat plan-OCR.md temuan #5 — nilai source tidak selalu sama dengan kode enum.
     */
    public function sourceKey(): string
    {
        return (string) (config("control-room.sites.{$this->value}.source_key") ?? $this->value);
    }
}
