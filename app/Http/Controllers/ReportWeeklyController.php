<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ReportWeeklyController extends Controller
{
    /**
     * Display a listing of the weekly reports
     */
    public function index()
    {
        return view('report-weekly.index');
    }

    /**
     * Display the specified weekly report
     */
    public function show($id)
    {
        return view('report-weekly.show', compact('id'));
    }
}

