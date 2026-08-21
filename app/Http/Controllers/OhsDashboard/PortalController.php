<?php

declare(strict_types=1);

namespace App\Http\Controllers\OhsDashboard;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class PortalController extends Controller
{
    public function index(): View
    {
        return view('OhsDashboard.pages.overview', [
            'page' => 'overview',
            'title' => 'Overview',
        ]);
    }

    public function leave(): View
    {
        return view('OhsDashboard.pages.leave-calendar', [
            'page' => 'leave',
            'title' => 'Leave & Integrated Calendar',
        ]);
    }

    public function events(): View
    {
        return view('OhsDashboard.pages.event-maker', [
            'page' => 'events',
            'title' => 'Event Maker',
        ]);
    }

    public function tracker(): View
    {
        return view('OhsDashboard.pages.tracker', [
            'page' => 'tracker',
            'title' => 'Project & Issue Tracker',
        ]);
    }

    public function admin(): View
    {
        return view('OhsDashboard.pages.admin', [
            'page' => 'admin',
            'title' => 'Admin Scheduler',
        ]);
    }
}
