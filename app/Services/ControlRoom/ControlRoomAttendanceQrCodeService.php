<?php

declare(strict_types=1);

namespace App\Services\ControlRoom;

use App\Enums\ControlRoomSiteCode;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * QR absensi per site — isi tautan form dengan query site, bukan form gabungan.
 */
final class ControlRoomAttendanceQrCodeService
{
    /**
     * @return list<array{site: ControlRoomSiteCode, url: string, svg: string}>
     */
    public function cards(): array
    {
        $cards = [];
        foreach (ControlRoomSiteDutyBoardService::BOARD_SITE_CODES as $code) {
            $site = ControlRoomSiteCode::from($code);
            $url = $this->attendanceFormUrl($site);
            $cards[] = [
                'site' => $site,
                'url' => $url,
                'svg' => $this->svg($url),
            ];
        }

        return $cards;
    }

    public function attendanceFormUrl(ControlRoomSiteCode $site): string
    {
        return route('control-room.attendance.form', ['site' => $site->value], true);
    }

    public function svg(string $content): string
    {
        return (string) QrCode::format('svg')
            ->size(240)
            ->margin(1)
            ->errorCorrection('M')
            ->generate($content);
    }
}
