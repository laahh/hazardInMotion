<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\OakCcv\GetOakCcvDashboardDataAction;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class OakCcvDashboardController extends Controller
{
    public function dashboard(Request $request, GetOakCcvDashboardDataAction $getDashboard): View
    {
        $filters = [
            'site' => trim((string) $request->query('site', '')),
            'week' => trim((string) $request->query('week', '')),
            'group' => trim((string) $request->query('group', 'all')),
            'entity' => trim((string) $request->query('entity', '')),
        ];

        return view('oak-ccv.dashboard', [
            'navActive' => 'overview',
            'dash' => $getDashboard($filters),
        ]);
    }
}
