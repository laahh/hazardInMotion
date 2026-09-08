<?php

declare(strict_types=1);

namespace App\Http\Controllers\ControlRoom;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ControlRoomTutorialController extends Controller
{
    private const EMBED_PATH = 'views/control-room/embed/index.html';

    public function index(): View
    {
        return view('control-room.tutorial.index', [
            'embedUrl' => route('control-room.tutorial.embed'),
        ]);
    }

    public function embed(): BinaryFileResponse
    {
        $path = resource_path(self::EMBED_PATH);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
