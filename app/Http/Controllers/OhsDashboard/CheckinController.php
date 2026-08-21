<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsDashboard;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class CheckinController extends Controller
{
    public function index(Request $request): View
    {
        return view('OhsDashboard.checkin.index', [
            'eventId' => trim((string) $request->query('eventId', '')),
        ]);
    }
}
