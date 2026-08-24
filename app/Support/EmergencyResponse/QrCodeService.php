<?php

declare(strict_types=1);

namespace App\Support\EmergencyResponse;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Wraps simplesoftwareio/simple-qrcode for Emergency Response asset labels.
 * Content is the scan URL so a phone camera opens the asset page directly.
 *
 * generate() returns Illuminate\Support\HtmlString; it must be cast to string
 * because this class uses declare(strict_types=1).
 */
class QrCodeService
{
    public function svg(string $content): string
    {
        return (string) QrCode::format('svg')
            ->size(400)
            ->margin(3)
            ->errorCorrection('H')
            ->generate($content);
    }

    public function png(string $content): string
    {
        return (string) QrCode::format('png')
            ->size(400)
            ->margin(3)
            ->errorCorrection('H')
            ->generate($content);
    }
}
