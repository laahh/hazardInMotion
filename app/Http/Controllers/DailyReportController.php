<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DailyReportController extends Controller
{
    /**
     * Display the PPT template generator page
     */
    public function index()
    {
        // Default values for the template
        $defaults = [
            'activity_tag1' => 'SDCER',
            'activity_tag2' => 'Routine Activity',
            'report_title' => 'Safety Daily Report',
            'site_name' => 'Site HO',
            'date' => date('d F Y'),
        ];

        return view('daily-report.index', compact('defaults'));
    }

    /**
     * Generate or preview the PPT template
     */
    public function generate(Request $request)
    {
        // Get all form data
        $data = $request->only([
            'activity_tag1',
            'activity_tag2',
            'report_title',
            'site_name',
            'date'
        ]);

        // Return view with data for preview/generation
        return view('daily-report.preview', compact('data'));
    }
}

