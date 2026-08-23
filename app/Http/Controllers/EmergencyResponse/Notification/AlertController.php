<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Notification;

use App\Http\Controllers\Controller;
use App\Models\EmergencyResponse\Notification\Alert;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(Request $request): View
    {
        $alerts = Alert::query()
            ->with('alertable')
            ->when($request->filled('alert_type'), fn ($query) => $query->where('alert_type', $request->query('alert_type')))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('EmergencyResponse.notification.alerts', ['alerts' => $alerts]);
    }
}
