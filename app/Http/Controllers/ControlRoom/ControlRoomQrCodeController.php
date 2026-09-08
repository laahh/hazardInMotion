<?php

declare(strict_types=1);

namespace App\Http\Controllers\ControlRoom;

use App\Http\Controllers\Controller;
use App\Services\ControlRoom\ControlRoomAttendanceQrCodeService;
use Illuminate\View\View;

final class ControlRoomQrCodeController extends Controller
{
    public function index(ControlRoomAttendanceQrCodeService $qrCodes): View
    {
        return view('control-room.qr-code.index', [
            'cards' => $qrCodes->cards(),
        ]);
    }
}
