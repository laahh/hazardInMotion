<?php

declare(strict_types=1);

namespace App\Http\Controllers\EmergencyResponse\Maintenance;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function index(): View
    {
        return view('EmergencyResponse.maintenance.index');
    }
}
