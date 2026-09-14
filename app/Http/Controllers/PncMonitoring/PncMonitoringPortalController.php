<?php

declare(strict_types=1);

namespace App\Http\Controllers\PncMonitoring;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class PncMonitoringPortalController extends Controller
{
    public function index(): View
    {
        return view('pnc-monitoring.portal');
    }
}
