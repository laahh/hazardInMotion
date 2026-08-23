<?php

declare(strict_types=1);

namespace App\Support\EmergencyResponse;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Wraps simplesoftwareio/simple-qrcode for Emergency Response asset codes
 * (emergency equipment, safety device). Content is the asset code itself,
 * not a URL — the scanning page looks the code up server-side.
 */
class QrCodeService
{
    public function svg(string $assetCode): string
    {
        return QrCode::format('svg')
            ->size(400)
            ->margin(3)
            ->errorCorrection('H')
            ->generate($assetCode);
    }

    public function png(string $assetCode): string
    {
        return QrCode::format('png')
            ->size(400)
            ->margin(3)
            ->errorCorrection('H')
            ->generate($assetCode);
    }
}
